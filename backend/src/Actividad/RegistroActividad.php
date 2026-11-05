<?php

declare(strict_types=1);

namespace App\Actividad;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Búfer de la bitácora de actividad de una petición: las filas se juntan en
 * memoria y se escriben en un solo `INSERT` al terminar (`volcar()`, desde
 * `kernel.terminate`, ya enviada la respuesta). Así la petición no espera a
 * la bitácora (ver el plan de la bitácora diaria).
 *
 * Las consultas automáticas (`sondeo`) no dejan una fila cada una: se
 * cuentan por usuario y ruta y se escriben juntas cada `VENTANA_SONDEO`
 * segundos. El conteo vive en el proceso (en el worker de FrankenPHP dura
 * entre peticiones); fuera del worker se escribe en cada petición.
 *
 * También da el `request_id` de la petición, que une la actividad con lo que
 * escribió en `bitacora`.
 */
final class RegistroActividad implements ResetInterface
{
    public const VENTANA_SONDEO = 60;

    /** Columnas de `actividad` en el orden del `INSERT` (sin `id`). */
    private const COLUMNAS = [
        "fecha", "usuario_id", "empresa_id", "estacion_id", "tipo", "accion", "metodo", "ruta",
        "estado", "duracion_ms", "ip", "agente", "request_id", "entidad", "registro_id", "detalle",
    ];

    /** @var list<array<string, mixed>> */
    private array $filas = [];
    private ?string $requestId = null;

    /** @var array<string, array{usuario: int, empresa: ?int, estacion: ?int, ruta: string, cantidad: int, desde: string, hasta: string}> */
    private static array $sondeos = [];
    private static float $sondeosDesde = 0.0;

    public function requestId(): string
    {
        return $this->requestId ??= self::uuid();
    }

    /** Fija el id que trajo la petición (`X-Request-Id`) si es un uuid válido. */
    public function usarRequestId(?string $id): void
    {
        if ($id !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            $this->requestId = strtolower($id);
        }
    }

    /**
     * @param array<string, mixed> $campos columnas de `actividad` (las que faltan van en null)
     */
    public function anotar(TipoActividad $tipo, array $campos): void
    {
        $fila = array_fill_keys(self::COLUMNAS, null);
        foreach ($campos as $k => $v) {
            if (array_key_exists($k, $fila)) {
                $fila[$k] = $v;
            }
        }
        $fila["tipo"] = $tipo->value;
        $fila["fecha"] ??= self::ahora();
        $fila["request_id"] ??= $this->requestId();
        $this->filas[] = $fila;
    }

    /** Una consulta automática más de este usuario a esta ruta. */
    public function contarSondeo(int $usuario, ?int $empresa, ?int $estacion, string $ruta): void
    {
        $clave = $usuario . "|" . $ruta;
        $ahora = self::ahora();
        if (self::$sondeos === []) {
            self::$sondeosDesde = microtime(true);
        }
        $actual = self::$sondeos[$clave] ?? ["usuario" => $usuario, "empresa" => $empresa, "estacion" => $estacion, "ruta" => $ruta, "cantidad" => 0, "desde" => $ahora, "hasta" => $ahora];
        $actual["cantidad"]++;
        $actual["hasta"] = $ahora;
        self::$sondeos[$clave] = $actual;
    }

    /** @return list<array<string, mixed>> lo pendiente de escribir (pruebas) */
    public function pendientes(): array
    {
        return $this->filas;
    }

    /**
     * Escribe lo juntado en un solo `INSERT` y vacía el búfer. Los sondeos,
     * si pasó su ventana (o siempre, `$todo`).
     *
     * @return int filas escritas
     */
    public function volcar(Connection $conexion, bool $todo = false): int
    {
        $filas = $this->filas;
        $this->filas = [];
        if (self::$sondeos !== [] && ($todo || microtime(true) - self::$sondeosDesde >= self::VENTANA_SONDEO)) {
            foreach (self::$sondeos as $s) {
                $filas[] = [
                    ...array_fill_keys(self::COLUMNAS, null),
                    "fecha" => $s["hasta"],
                    "usuario_id" => $s["usuario"],
                    "empresa_id" => $s["empresa"],
                    "estacion_id" => $s["estacion"],
                    "tipo" => TipoActividad::SONDEO->value,
                    "accion" => "consultas",
                    "ruta" => $s["ruta"],
                    "detalle" => json_encode(["cantidad" => $s["cantidad"], "desde" => $s["desde"]], JSON_THROW_ON_ERROR),
                ];
            }
            self::$sondeos = [];
        }
        if ($filas === []) {
            return 0;
        }

        $valores = [];
        $parametros = [];
        $tipos = [];
        foreach ($filas as $fila) {
            $marcas = [];
            foreach (self::COLUMNAS as $c) {
                $marcas[] = match ($c) {
                    "ip" => "CAST(? AS INET)",
                    "request_id" => "CAST(? AS UUID)",
                    "detalle" => "CAST(? AS JSONB)",
                    default => "?",
                };
                $parametros[] = $fila[$c];
                $tipos[] = is_int($fila[$c]) ? ParameterType::INTEGER : ($fila[$c] === null ? ParameterType::NULL : ParameterType::STRING);
            }
            $valores[] = "(" . implode(", ", $marcas) . ")";
        }
        $conexion->executeStatement(
            "INSERT INTO actividad (" . implode(", ", self::COLUMNAS) . ") VALUES " . implode(", ", $valores),
            $parametros,
            $tipos,
        );

        return count($filas);
    }

    public function reset(): void
    {
        $this->filas = [];
        $this->requestId = null;
    }

    /** Fuera del worker no hay "después": los sondeos se escriben en cada petición. */
    public static function enWorker(): bool
    {
        return isset($_SERVER["FRANKENPHP_WORKER"]) || getenv("FRANKENPHP_WORKER") !== false;
    }

    /** @internal pruebas */
    public static function olvidarSondeos(): void
    {
        self::$sondeos = [];
    }

    private static function ahora(): string
    {
        return (new \DateTimeImmutable())->format("Y-m-d H:i:s.vP");
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0F) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3F) | 0x80);

        return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($b), 4));
    }
}
