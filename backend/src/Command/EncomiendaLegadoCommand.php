<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Copia del legado lo que cotiza e imprime una encomienda (ADR-035): los
 * tipos especiales con su tarifa vigente, la escala de peso vigente, los
 * recargos por distancia entre estaciones y la sigla de cada estación (las
 * estaciones conservan su id). Se puede repetir: actualiza los especiales por
 * `legacyId` y reemplaza la escala y los recargos.
 *
 * Los rangos de peso del legado (0–10, 10.01–20…) quedan encadenados: «más
 * de 10 hasta 20».
 */
#[AsCommand(name: "app:encomienda:legado", description: "Copia del legado los especiales, las tarifas de encomiendas y las siglas de estación")]
final class EncomiendaLegadoCommand
{
    public function __construct(
        private readonly Connection $conexion,
        #[Target("oldPdo")] private readonly \PDO $legado,
    ) {
        $this->legado->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->legado->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "Solo mostrar lo que se copiaría")] bool $simular = false,
    ): int {
        $especiales = $this->especiales();
        $escala = $this->escalaPeso();
        $recargos = $this->recargos();
        $aliases = $this->legado->query("SELECT id, alias FROM estacion WHERE alias IS NOT NULL AND LTRIM(RTRIM(alias)) <> ''")->fetchAll();

        $io->definitionList(
            ["Especiales" => count($especiales)],
            ["Rangos de peso" => count($escala)],
            ["Recargos por distancia" => count($recargos)],
            ["Siglas de estación" => count($aliases)],
        );
        if ($simular) {
            $io->table(["Desde", "Hasta", "Precio"], array_map(static fn(array $r) => [$r["desde"], $r["hasta"] ?? "…", $r["precio"] / 100], $escala));

            return 0;
        }

        $this->conexion->transactional(function () use ($especiales, $escala, $recargos, $aliases) {
            foreach ($aliases as $a) {
                $this->conexion->executeStatement(
                    "UPDATE enclave SET alias = :alias WHERE id = :id AND alias IS NULL",
                    ["alias" => mb_strtoupper(mb_substr(trim(self::utf8((string) $a["alias"])), 0, 10)), "id" => (int) $a["id"]],
                );
            }

            foreach ($especiales as $e) {
                $existe = $this->conexion->fetchOne("SELECT id FROM encomienda_especial WHERE legacy_id = ?", [$e["legacy"]]);
                $datos = [
                    "nombre" => $e["nombre"],
                    "descripcion" => $e["descripcion"],
                    "precio_monto" => $e["precio"],
                    "precio_moneda" => "GTQ",
                    "permite_por_cobrar" => $e["porCobrar"] ? "true" : "false",
                    "activo" => $e["activo"] ? "true" : "false",
                ];
                $existe === false
                    ? $this->conexion->insert("encomienda_especial", [...$datos, "legacy_id" => $e["legacy"]])
                    : $this->conexion->update("encomienda_especial", $datos, ["id" => $existe]);
            }

            $this->conexion->executeStatement("DELETE FROM tarifa_encomienda_peso");
            foreach ($escala as $r) {
                $this->conexion->insert("tarifa_encomienda_peso", [
                    "peso_desde" => $r["desde"],
                    "peso_hasta" => $r["hasta"],
                    "precio_monto" => $r["precio"],
                    "precio_moneda" => "GTQ",
                    "vigente_desde" => $r["vigente"],
                ]);
            }

            $this->conexion->executeStatement("DELETE FROM tarifa_encomienda_distancia");
            foreach ($recargos as $r) {
                $this->conexion->insert("tarifa_encomienda_distancia", [
                    "origen_id" => $r["origen"],
                    "destino_id" => $r["destino"],
                    "precio_monto" => $r["precio"],
                    "precio_moneda" => "GTQ",
                    "vigente_desde" => $r["vigente"],
                ]);
            }
        });

        $io->success("Especiales, tarifas de encomiendas y siglas de estación copiados del legado.");

        return 0;
    }

    /**
     * Cada tipo especial con su tarifa más reciente ya vigente (sin tarifa: inactivo, a Q0).
     *
     * @return list<array{legacy: string, nombre: string, descripcion: ?string, precio: int, porCobrar: bool, activo: bool}>
     */
    private function especiales(): array
    {
        $filas = $this->legado->query(
            "SELECT t.id, t.nombre, t.descripcion, t.activo, t.permitePorCobrar,
                    (SELECT TOP 1 x.tarifaValor FROM tarifas_encomienda_especiales x
                      WHERE x.tipo_encomienda_especial_id = t.id AND x.fechaEfectividad <= GETDATE()
                      ORDER BY x.fechaEfectividad DESC, x.id DESC) AS precio
               FROM encomienda_especiales_tipo t ORDER BY t.id",
        )->fetchAll();

        return array_map(static fn(array $f) => [
            "legacy" => (string) $f["id"],
            "nombre" => mb_substr(trim(self::utf8((string) $f["nombre"])), 0, 100),
            "descripcion" => trim(self::utf8((string) $f["descripcion"])) ?: null,
            "precio" => (int) round((float) $f["precio"] * 100),
            "porCobrar" => (bool) $f["permitePorCobrar"],
            "activo" => (bool) $f["activo"] && $f["precio"] !== null,
        ], $filas);
    }

    /**
     * Para cada rango, la tarifa más reciente; los rangos se encadenan por su tope.
     *
     * @return list<array{desde: float, hasta: ?float, precio: int, vigente: string}>
     */
    private function escalaPeso(): array
    {
        $filas = $this->legado->query(
            "SELECT p.pesoMinimo, p.pesoMaximo, p.tarifaValor, p.fechaEfectividad
               FROM tarifas_encomienda_paquetes_peso p
              WHERE p.fechaEfectividad <= GETDATE()
              ORDER BY p.fechaEfectividad DESC, p.id DESC",
        )->fetchAll();

        // Por tope del rango, el más reciente.
        $porTope = [];
        foreach ($filas as $f) {
            $tope = $f["pesoMaximo"] === null ? "∞" : (string) (float) $f["pesoMaximo"];
            $porTope[$tope] ??= $f;
        }
        uksort($porTope, static fn($a, $b) => ($a === "∞" ? INF : (float) $a) <=> ($b === "∞" ? INF : (float) $b));

        $escala = [];
        $desde = 0.0;
        foreach ($porTope as $f) {
            $hasta = $f["pesoMaximo"] === null ? null : (float) $f["pesoMaximo"];
            $escala[] = [
                "desde" => $desde,
                "hasta" => $hasta,
                "precio" => (int) round((float) $f["tarifaValor"] * 100),
                "vigente" => (new \DateTimeImmutable((string) $f["fechaEfectividad"]))->format("Y-m-d H:i:s"),
            ];
            $desde = $hasta ?? $desde;
        }

        return $escala;
    }

    /**
     * El recargo más reciente de cada par de estaciones (en cualquier sentido)
     * que existan en el modelo nuevo; sin los de Q0.
     *
     * @return list<array{origen: int, destino: int, precio: int, vigente: string}>
     */
    private function recargos(): array
    {
        $existen = array_flip(array_map("intval", $this->conexion->fetchFirstColumn("SELECT id FROM enclave")));
        $filas = $this->legado->query(
            "SELECT estacion_origen_id, estacion_destino_id, tarifaValor, fechaEfectividad
               FROM tarifas_encomienda_distancia
              WHERE fechaEfectividad <= GETDATE()
              ORDER BY fechaEfectividad DESC, id DESC",
        )->fetchAll();

        $pares = [];
        foreach ($filas as $f) {
            $a = (int) $f["estacion_origen_id"];
            $b = (int) $f["estacion_destino_id"];
            $clave = min($a, $b) . "-" . max($a, $b);
            if (isset($pares[$clave]) || !isset($existen[$a], $existen[$b])) {
                continue;
            }
            $pares[$clave] = [
                "origen" => $a,
                "destino" => $b,
                "precio" => (int) round((float) $f["tarifaValor"] * 100),
                "vigente" => (new \DateTimeImmutable((string) $f["fechaEfectividad"]))->format("Y-m-d H:i:s"),
            ];
        }

        return array_values(array_filter($pares, static fn(array $r) => $r["precio"] > 0));
    }

    /** El legado entrega Latin-1 por el driver dblib. */
    private static function utf8(string $s): string
    {
        return mb_check_encoding($s, "UTF-8") ? $s : mb_convert_encoding($s, "UTF-8", "ISO-8859-1");
    }
}
