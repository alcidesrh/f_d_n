<?php

declare(strict_types=1);

namespace App\Migration\Entidad;

use App\Migration\Migrador;
use App\Migration\MigradorEstaticos;
use App\Migration\MigradorIAM;
use App\Services\EntityConfigSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Catálogo de migradores por entidad + orden del flujo "estáticos".
 *
 * Nombres canónicos: empresa, estacion, localidad, marca, piloto, cliente,
 * usuario, bus, asiento, senal, trayecto, tarifa, salida, iam, config.
 *
 * La entidad "salida" se marca SIN dependencias porque migrarSalida ya recorre
 * sus propias ramificaciones (empresa, trayecto, bus, cliente…) con dedupe:
 * ejecutarla en solitario es seguro.
 *
 * #[Lazy]: el catálogo (y la conexión SQL Server legacy que arrastra
 * MigradorEstaticos/MigradorIAM) solo se resuelve al primer uso; /estado,
 * /ejecutar, /cancelar y /log del panel funcionan aunque el legado no responda.
 *
 * (No es final para permitir su mock en tests unitarios de EjecutorEntidad.)
 */
#[Lazy]
class RegistroMigradores
{
    /** Orden del flujo completo de estáticos (mismo que MigradorEstaticos::migrar). */
    public const ORDEN_ESTATICOS = [
        "empresa",
        "piloto",
        "localidad",
        "estacion",
        "cliente",
        "usuario",
        "marca",
        "bus",
        "asiento",
        "senal",
        "trayecto",
        "tarifa",
    ];

    /** @var array<string, MigradorEntidadInterface>|null */
    private ?array $catalogo = null;

    public function __construct(
        private readonly MigradorEstaticos $estaticos,
        private readonly Migrador $migrador,
        private readonly MigradorIAM $iam,
        private readonly EntityConfigSynchronizer $configSynchronizer,
        private readonly EntityManagerInterface $em,
        #[Target("oldPdo")] private readonly \PDO $oldPdo,
    ) {}

    /**
     * @return array<string, MigradorEntidadInterface>
     */
    public function todos(): array
    {
        if (null === $this->catalogo) {
            $this->catalogo = $this->construir();
        }

        return $this->catalogo;
    }

    public function tiene(string $nombre): bool
    {
        return isset($this->todos()[$nombre]);
    }

    public function obtener(string $nombre): MigradorEntidadInterface
    {
        if (!$this->tiene($nombre)) {
            throw new \InvalidArgumentException(
                "Migrador de entidad desconocido: {$nombre}",
            );
        }

        return $this->todos()[$nombre];
    }

    /**
     * @return array<string, MigradorEntidadInterface>
     */
    private function construir(): array
    {
        $estatica = function (
            string $nombre,
            string $etiqueta,
            string $tablaFuente,
            array $tablasDestino,
            array $dependencias = [],
            ?string $sqlConteoFuente = null,
            bool $soportaCantidad = false,
        ): MigradorEntidadInterface {
            return new MigradorEntidadEstatica(
                $this->estaticos,
                $nombre,
                $etiqueta,
                $tablaFuente,
                $tablasDestino,
                $dependencias,
                $soportaCantidad,
                $sqlConteoFuente,
                $this->oldPdo,
            );
        };

        $catalogo = [
            "empresa" => $estatica(
                "empresa",
                "Empresas",
                "empresa",
                ["empresa"],
                [],
                "SELECT COUNT(*) FROM empresa WHERE activo = 1",
            ),
            "estacion" => $estatica(
                "estacion",
                "Estaciones",
                "estacion",
                ["enclave"],
                [],
                "SELECT COUNT(*) FROM estacion WHERE activo = 1",
            ),
            "localidad" => $estatica(
                "localidad",
                "Localidades",
                "departamento",
                ["localidad"],
            ),
            "marca" => $estatica("marca", "Marcas de bus", "bus_marca", [
                "bus_marca",
            ]),
            "piloto" => $estatica("piloto", "Pilotos", "piloto", ["piloto"]),
            "cliente" => $estatica(
                "cliente",
                "Clientes",
                "cliente",
                ["cliente"],
                [],
                null,
                true,
            ),
            "usuario" => $estatica("usuario", "Usuarios", "custom_user", [
                "usuario",
                "api_token",
            ]),
            "bus" => $estatica(
                "bus",
                "Buses",
                "bus",
                ["bus"],
                ["empresa", "marca"],
            ),
            // Asientos y señales son del tipo de bus en el legado: se cuentan
            // por bus (cada bus recibe su copia).
            "asiento" => $estatica(
                "asiento",
                "Asientos",
                "bus_asiento",
                ["asiento"],
                ["bus"],
                "SELECT COUNT(*) FROM bus_asiento ba JOIN bus b ON b.tipo_id = ba.tipoBus_id",
            ),
            "senal" => $estatica(
                "senal",
                "Chofer y puertas",
                "bus_senal",
                ["bus_senal"],
                ["bus"],
                "SELECT COUNT(*) FROM bus_senal s JOIN bus b ON b.tipo_id = s.tipoBus_id",
            ),
            "trayecto" => $estatica(
                "trayecto",
                "Trayectos",
                "ruta",
                ["trayecto", "subtrayecto"],
                ["estacion"],
            ),
            "tarifa" => $estatica(
                "tarifa",
                "Tarifas",
                "tarifas_boleto",
                ["boleto_tarifa"],
                ["empresa", "trayecto", "usuario"],
            ),
            "salida" => new MigradorEntidadSalida($this->migrador),
            "iam" => new MigradorEntidadIam($this->iam),
            "config" => new MigradorEntidadConfig(
                $this->configSynchronizer,
                $this->em,
            ),
        ];

        return $catalogo;
    }
}
