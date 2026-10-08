<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Conteos "nuevo" (PostgreSQL) vs "legado" (SQL Server) por entidad migrable,
 * para el panel de indicadores de /migracion.
 *
 * El PDO legacy se crea bajo demanda (crearPdo con preflight TCP de
 * SondaLegado) y dentro del try de contarLegado, de modo que el panel degrada a
 * -1 sin colgarse cuando el SQL Server legacy no responde. Como el puerto puede
 * abrir aunque el servidor no conteste consultas, cada conteo tiene un tope
 * (QUERY_TIMEOUT) y tras el primer fallo no se intentan los demás. Los conteos
 * del legado se cachean (el panel los pide cada pocos segundos y cada pedido
 * ocupa un worker de FrankenPHP: sin esto, un legado lento deja sin workers a
 * toda la app).
 */
#[Lazy]
final class IndicadoresMigracion
{
    /**
     * Tarifas del legado que se migran: la más reciente de cada grupo de
     * iguales (ver `MigradorEstaticos::migrarTarifas`). No descuenta las que
     * no tienen trayecto en el modelo nuevo.
     */
    public const LEGADO_TARIFAS = "SELECT COUNT(*) FROM (SELECT DISTINCT estacion_origen_id, estacion_destino_id, clase_asiento_id, clase_bus_id, horaInicialSalida, horaFinalSalida FROM tarifas_boleto) t";

    /** @var array<string, array{nuevo: string, legado: ?string}> */
    private const CONTEOS = [
        "empresa" => [
            "nuevo" => "SELECT COUNT(*) FROM empresa",
            "legado" => "SELECT COUNT(*) FROM empresa WHERE activo = 1",
        ],
        "estacion" => [
            "nuevo" => "SELECT COUNT(*) FROM enclave WHERE tipo = 'estacion'",
            "legado" => "SELECT COUNT(*) FROM estacion WHERE activo = 1 AND (tipoEstacion_id IS NULL OR tipoEstacion_id <> 4)",
        ],
        "localidad" => [
            "nuevo" => "SELECT COUNT(*) FROM localidad",
            "legado" => "SELECT COUNT(*) FROM departamento",
        ],
        "marca" => [
            "nuevo" => "SELECT COUNT(*) FROM bus_marca",
            "legado" => "SELECT COUNT(*) FROM bus_marca",
        ],
        "bus_clase" => [
            "nuevo" => "SELECT COUNT(*) FROM bus_clase",
            "legado" => "SELECT COUNT(*) FROM bus_clase",
        ],
        "piloto" => [
            "nuevo" => "SELECT COUNT(*) FROM piloto",
            "legado" => "SELECT COUNT(*) FROM piloto",
        ],
        "tipo_pago" => [
            "nuevo" => "SELECT COUNT(*) FROM tipo_pago",
            "legado" => "SELECT COUNT(*) FROM tipo_pago",
        ],
        "moneda" => [
            "nuevo" => "SELECT COUNT(*) FROM moneda",
            "legado" => "SELECT COUNT(*) FROM moneda",
        ],
        "tipo_documento" => [
            "nuevo" => "SELECT COUNT(*) FROM tipo_documento",
            "legado" => "SELECT COUNT(*) FROM tipo_documento",
        ],
        "nacionalidad" => [
            "nuevo" => "SELECT COUNT(*) FROM pais",
            "legado" => "SELECT COUNT(*) FROM nacionalidad",
        ],
        "fel" => [
            "nuevo" => "SELECT COUNT(*) FROM credencial_fel",
            "legado" => "SELECT COUNT(*) FROM factura_emisor",
        ],
        "agencia" => [
            "nuevo" => "SELECT COUNT(*) FROM agencia",
            "legado" => "SELECT COUNT(*) FROM estacion WHERE tipoEstacion_id = 4",
        ],
        "cliente" => [
            "nuevo" => "SELECT COUNT(*) FROM cliente",
            "legado" => "SELECT COUNT(*) FROM cliente",
        ],
        "usuario" => [
            "nuevo" => "SELECT COUNT(*) FROM usuario",
            "legado" => "SELECT COUNT(*) FROM custom_user",
        ],
        "bus" => [
            "nuevo" => "SELECT COUNT(*) FROM bus",
            "legado" => "SELECT COUNT(*) FROM bus",
        ],
        "asiento" => [
            "nuevo" => "SELECT COUNT(*) FROM asiento",
            "legado" =>
                "SELECT COUNT(*) FROM bus_asiento ba JOIN bus b ON b.tipo_id = ba.tipoBus_id",
        ],
        "senal" => [
            "nuevo" => "SELECT COUNT(*) FROM bus_senal",
            "legado" =>
                "SELECT COUNT(*) FROM bus_senal s JOIN bus b ON b.tipo_id = s.tipoBus_id",
        ],
        "trayecto" => [
            "nuevo" => "SELECT COUNT(*) FROM trayecto",
            "legado" => "SELECT COUNT(*) FROM ruta",
        ],
        "tarifa" => [
            "nuevo" => "SELECT COUNT(*) FROM boleto_tarifa",
            "legado" => self::LEGADO_TARIFAS,
        ],
        "salida" => [
            "nuevo" => "SELECT COUNT(*) FROM salida",
            "legado" => "SELECT COUNT(*) FROM salida",
        ],
        "boleto" => [
            "nuevo" => "SELECT COUNT(*) FROM boleto_asiento",
            "legado" => "SELECT COUNT(*) FROM boleto",
        ],
        "iam" => [
            "nuevo" =>
                "SELECT (SELECT COUNT(*) FROM action) + (SELECT COUNT(*) FROM permiso) + (SELECT COUNT(*) FROM role)",
            "legado" => "SELECT COUNT(*) FROM custom_rol",
        ],
        "config" => [
            "nuevo" => "SELECT COUNT(*) FROM entity_configuration",
            "legado" => null,
        ],
    ];

    /** Segundos que se reutilizan los conteos del legado (cambian despacio). */
    private const TTL_LEGADO = 300;

    /** Si el legado falló, se vuelve a intentar antes. */
    private const TTL_LEGADO_FALLIDO = 30;

    /** Tope por consulta al legado, en segundos. */
    private const QUERY_TIMEOUT = 10;

    private ?\PDO $pdoLegadoCache = null;

    public function __construct(
        private readonly Connection $newConn,
        private readonly SondaLegado $sonda,
        #[Target("cache.migracion")] private readonly CacheInterface $cache,
    ) {}

    /**
     * @return array<string, array{nuevo: int, legado: int}>
     */
    public function todos(): array
    {
        $resultado = [];
        $totalNuevo = 0;
        $totalLegado = 0;

        $legados = $this->conteosLegado();
        foreach (self::CONTEOS as $clave => $sqls) {
            $nuevo = $this->contarNuevo($sqls["nuevo"]);
            $legado = $legados[$clave] ?? 0;
            $totalNuevo += max(0, $nuevo);
            $totalLegado += max(0, $legado);
            $resultado[$clave] = ["nuevo" => $nuevo, "legado" => $legado];
        }

        $resultado["total"] = [
            "nuevo" => $totalNuevo,
            "legado" => $totalLegado,
        ];

        return $resultado;
    }

    private function contarNuevo(string $sql): int
    {
        try {
            return (int) $this->newConn->fetchOne($sql);
        } catch (\Throwable) {
            return -1;
        }
    }

    /**
     * Conteos del legado por entidad, cacheados. Tras el primer fallo
     * (legado caído o lento) los demás quedan en -1 sin consultarlo.
     *
     * @return array<string, int>
     */
    private function conteosLegado(): array
    {
        return $this->cache->get("migracion.indicadores.legado", function (ItemInterface $item): array {
            $conteos = [];
            $fallo = false;
            foreach (self::CONTEOS as $clave => $sqls) {
                if (null === $sqls["legado"]) {
                    $conteos[$clave] = 0;
                    continue;
                }
                $conteos[$clave] = $fallo ? -1 : $this->contarLegado($sqls["legado"]);
                $fallo = $fallo || $conteos[$clave] < 0;
            }
            $item->expiresAfter($fallo ? self::TTL_LEGADO_FALLIDO : self::TTL_LEGADO);

            return $conteos;
        });
    }

    private function contarLegado(string $sql): int
    {
        try {
            $stmt = $this->pdoLegado()->prepare($sql);
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return -1;
        }
    }

    private function pdoLegado(): \PDO
    {
        return $this->pdoLegadoCache ??= $this->sonda->crearPdo([
            \PDO::DBLIB_ATTR_QUERY_TIMEOUT => self::QUERY_TIMEOUT,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
