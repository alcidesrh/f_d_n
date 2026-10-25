<?php

declare(strict_types=1);

namespace App\Sincronizacion\Legado;

use App\Migration\SondaLegado;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Conexión propia de la sincronización al SQL Server del legado (ADR-042),
 * aparte de la de la migración. Su sesión lleva `CONTEXT_INFO 'FDNSYNC'`:
 * los triggers del legado no anotan lo que escribe FDN, así un cambio no
 * vuelve al sistema de donde salió.
 *
 * Habla UTF-8 con el servidor (`charset` de FreeTDS): sin conversiones a mano.
 *
 * Con `SINCRONIZACION_SOLO_TRAER=1` (un FDN de desarrollo contra el legado de
 * producción) solo escribe en las tablas `sync_*` y en el esquema (instalar
 * los triggers): nunca un boleto, un cliente o una encomienda.
 */
class ConexionLegado
{
    /** `CONTEXT_INFO` de las escrituras de FDN ('FDNSYNC'); lo comparan los triggers. */
    public const MARCA = "0x46444E53594E43";

    private ?\PDO $pdo = null;

    public function __construct(
        private readonly SondaLegado $sonda,
        #[Autowire(env: "systemfdn_host")] private readonly string $host,
        #[Autowire(env: "systemfdn_port")] private readonly string $port,
        #[Autowire(env: "systemfdn_dbname")] private readonly string $dbname,
        #[Autowire(env: "systemfdn_user")] private readonly string $usuario,
        #[Autowire(env: "systemfdn_password")] private readonly string $clave,
        #[Autowire(env: "bool:SINCRONIZACION_SOLO_TRAER")] public readonly bool $soloTraer = false,
    ) {}

    /**
     * En modo «solo traer», rechaza la sentencia que escriba en una tabla que
     * no sea `sync_*`. El DDL (CREATE/DROP TRIGGER, ALTER TABLE) pasa: es la
     * instalación.
     *
     * @throws \LogicException
     */
    public static function exigirSoloSync(string $sql): void
    {
        preg_match_all('/\b(?:INSERT\s+(?:INTO\s+)?|UPDATE\s+|DELETE\s+(?:FROM\s+)?|MERGE\s+(?:INTO\s+)?)(?:\[?dbo\]?\.)?\[?(@?\w+)/i', $sql, $m);
        foreach ($m[1] as $tabla) {
            // `AFTER INSERT, UPDATE AS` de un trigger; `@ids`, variable de tabla.
            if (!str_starts_with(strtolower($tabla), "sync_") && !str_starts_with($tabla, "@") && strtoupper($tabla) !== "AS") {
                throw new \LogicException("Sincronización en modo «solo traer»: no se escribe en «{$tabla}» del legado.");
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function filas(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(self::parametros($params));

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fila(string $sql, array $params = []): ?array
    {
        return $this->filas($sql, $params)[0] ?? null;
    }

    /** @param array<string, mixed> $params */
    public function valor(string $sql, array $params = []): mixed
    {
        $fila = $this->fila($sql, $params);

        return $fila === null ? null : reset($fila);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return int filas afectadas
     */
    public function ejecutar(string $sql, array $params = []): int
    {
        if ($this->soloTraer) {
            self::exigirSoloSync($sql);
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(self::parametros($params));

        return $stmt->rowCount();
    }

    /**
     * INSERT de una fila; devuelve su id. Con `OUTPUT ... INTO` porque las
     * tablas con trigger no admiten `OUTPUT` a secas.
     *
     * @param array<string, mixed> $datos
     */
    public function insertar(string $tabla, array $datos): int
    {
        if ($this->soloTraer) {
            self::exigirSoloSync("INSERT INTO [{$tabla}]");
        }
        $campos = implode(", ", array_map(static fn(string $c) => "[{$c}]", array_keys($datos)));
        $args = implode(", ", array_map(static fn(string $c) => ":{$c}", array_keys($datos)));
        $stmt = $this->pdo()->prepare(
            "SET NOCOUNT ON; DECLARE @ids TABLE (id BIGINT);
             INSERT INTO [{$tabla}] ({$campos}) OUTPUT INSERTED.id INTO @ids VALUES ({$args});
             SELECT id FROM @ids",
        );
        $stmt->execute(self::parametros($datos));
        // dblib entrega un resultado (vacío) por sentencia del lote: el id está en el último.
        do {
            $fila = $stmt->columnCount() > 0 ? $stmt->fetch() : false;
            if ($fila !== false) {
                return (int) reset($fila);
            }
        } while ($stmt->nextRowset());

        throw new \RuntimeException("El legado no devolvió el id de la fila insertada en {$tabla}.");
    }

    /**
     * @template T
     *
     * @param callable(self): T $operacion
     *
     * @return T
     */
    public function transaccion(callable $operacion): mixed
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) {
            // Anidada: es parte de la externa (un solo commit, un solo rollback).
            return $operacion($this);
        }
        $pdo->beginTransaction();
        try {
            $resultado = $operacion($this);
            $pdo->commit();

            return $resultado;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function accesible(): bool
    {
        return $this->sonda->accesible();
    }

    /** Tras un error de conexión: la próxima operación abre otra. */
    public function cerrar(): void
    {
        $this->pdo = null;
    }

    private function pdo(): \PDO
    {
        if ($this->pdo === null) {
            if (!$this->sonda->accesible()) {
                throw new \PDOException("El SQL Server del legado no responde en {$this->host}:{$this->port}.");
            }
            $pdo = new \PDO("dblib:host={$this->host}:{$this->port};dbname={$this->dbname};charset=UTF-8", $this->usuario, $this->clave);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $pdo->prepare("SET CONTEXT_INFO " . self::MARCA)->execute();
            $this->pdo = $pdo;
        }

        return $this->pdo;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private static function parametros(array $params): array
    {
        foreach ($params as $k => $v) {
            $params[$k] = match (true) {
                is_bool($v) => $v ? 1 : 0,
                // ISO 8601 con la T: no depende del idioma ni del DATEFORMAT de la sesión.
                $v instanceof \DateTimeInterface => $v->format("Y-m-d\\TH:i:s"),
                default => $v,
            };
        }

        return $params;
    }
}
