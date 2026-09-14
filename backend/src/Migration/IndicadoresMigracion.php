<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

/**
 * Conteos "nuevo" (PostgreSQL) vs "legado" (SQL Server) por entidad migrable,
 * para el panel de indicadores de /migracion.
 *
 * El PDO legacy se crea bajo demanda (crearPdo con preflight TCP de
 * SondaLegado) y dentro del try de contarLegado, de modo que el panel degrada a
 * -1 sin colgarse cuando el SQL Server legacy no responde.
 */
#[Lazy]
final class IndicadoresMigracion
{
    /** @var array<string, array{nuevo: string, legado: ?string}> */
    private const CONTEOS = [
        "empresa" => [
            "nuevo" => "SELECT COUNT(*) FROM empresa",
            "legado" => "SELECT COUNT(*) FROM empresa WHERE activo = 1",
        ],
        "estacion" => [
            "nuevo" => "SELECT COUNT(*) FROM enclave WHERE tipo = 'estacion'",
            "legado" => "SELECT COUNT(*) FROM estacion WHERE activo = 1",
        ],
        "localidad" => [
            "nuevo" => "SELECT COUNT(*) FROM localidad",
            "legado" => "SELECT COUNT(*) FROM departamento",
        ],
        "marca" => [
            "nuevo" => "SELECT COUNT(*) FROM bus_marca",
            "legado" => "SELECT COUNT(*) FROM bus_marca",
        ],
        "piloto" => [
            "nuevo" => "SELECT COUNT(*) FROM piloto",
            "legado" => "SELECT COUNT(*) FROM piloto",
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
            "legado" => "SELECT COUNT(*) FROM bus_asiento",
        ],
        "trayecto" => [
            "nuevo" => "SELECT COUNT(*) FROM trayecto",
            "legado" => "SELECT COUNT(*) FROM ruta",
        ],
        "tarifa" => [
            "nuevo" => "SELECT COUNT(*) FROM boleto_tarifa",
            "legado" => "SELECT COUNT(*) FROM tarifas_boleto",
        ],
        "salida" => [
            "nuevo" => "SELECT COUNT(*) FROM itinerario",
            "legado" => "SELECT COUNT(*) FROM salida WHERE estado_id IN (1,2)",
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

    private ?\PDO $pdoLegadoCache = null;

    public function __construct(
        private readonly Connection $newConn,
        private readonly SondaLegado $sonda,
    ) {}

    /**
     * @return array<string, array{nuevo: int, legado: int}>
     */
    public function todos(): array
    {
        $resultado = [];
        $totalNuevo = 0;
        $totalLegado = 0;

        foreach (self::CONTEOS as $clave => $sqls) {
            $nuevo = $this->contarNuevo($sqls["nuevo"]);
            $legado =
                null !== $sqls["legado"]
                    ? $this->contarLegado($sqls["legado"])
                    : 0;
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
        return $this->pdoLegadoCache ??= $this->sonda->crearPdo();
    }
}
