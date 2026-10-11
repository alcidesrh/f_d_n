<?php

declare(strict_types=1);

namespace App\Migration\Salida;

use App\Entity\Trayecto;
use App\Ruta\Horarios;
use App\Ruta\RutaDeTrayecto;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Completa una base migrada antes de ADR-030 (transitorio: se retira con
 * `Subtrayecto`, fase 8):
 *
 * 1. Migra todas las rutas del legado con sus paradas y tiempos.
 * 2. A cada salida del legado le asigna la ruta de su itinerario del legado.
 *    Las creadas en el sistema nuevo (sin legado) toman la ruta con las
 *    mismas puntas que más salidas tiene (`RutaDeTrayecto`).
 * 3. Fija el horario de todas las salidas (`Horarios`).
 * 4. Pone el tramo por orden a boletos y reservas a partir de su trayecto
 *    (el par que se migró del legado); fuera de la ruta, la salida completa.
 * 5. Asigna la ruta a los esquemas de salidas por el mismo criterio que 2.
 */
final class RellenoRutas
{
    private const LOTE = 500;

    public function __construct(
        private readonly Connection $db,
        private readonly LectorLegado $legado,
        private readonly RutasLegado $rutas,
        private readonly Horarios $horarios,
        private readonly RutaDeTrayecto $rutaDeTrayecto,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return array<string, int> */
    public function rellenar(?callable $avance = null): array
    {
        $c = ["rutas" => $this->rutas->migrarTodas()];
        $avance && $avance("Rutas del legado: {$c["rutas"]}");

        $c += $this->rutasDeSalidasLegado();
        $avance && $avance("Salidas con la ruta del legado: {$c["salidas_legado"]}");
        $c += $this->rutasPorPuntas("salida");
        $c += $this->rutasPorPuntas("esquema_salida");

        $ids = array_map("intval", $this->db->fetchFirstColumn("SELECT id FROM salida WHERE ruta_id IS NOT NULL ORDER BY id"));
        foreach (array_chunk($ids, self::LOTE) as $i => $lote) {
            $this->horarios->fijar(...$lote);
            $avance && $avance(sprintf("Horarios: %d/%d", min(($i + 1) * self::LOTE, count($ids)), count($ids)));
        }
        $c["horarios"] = count($ids);

        foreach (["boleto_asiento", "reserva_asiento"] as $tabla) {
            $c["{$tabla}_en_ruta"] = $this->db->executeStatement(
                "UPDATE {$tabla} x SET orden_sube = sp_o.orden, orden_baja = sp_d.orden
                   FROM trayecto t, salida_parada sp_o, salida_parada sp_d
                  WHERE t.id = x.trayecto_id
                    AND sp_o.salida_id = x.salida_id AND sp_o.enclave_id = t.origen_id
                    AND sp_d.salida_id = x.salida_id AND sp_d.enclave_id = t.destino_id
                    AND sp_o.orden < sp_d.orden AND x.orden_sube IS NULL",
            );
            $c["{$tabla}_fuera_de_ruta"] = $this->db->executeStatement(
                "UPDATE {$tabla} x SET orden_sube = 0, orden_baja = u.ultima
                   FROM (SELECT salida_id, MAX(orden) AS ultima FROM salida_parada GROUP BY salida_id) u
                  WHERE u.salida_id = x.salida_id AND x.orden_sube IS NULL",
            );
        }
        $c["salidas_sin_ruta"] = (int) $this->db->fetchOne("SELECT COUNT(*) FROM salida WHERE ruta_id IS NULL");
        $c["boletos_sin_tramo"] = (int) $this->db->fetchOne("SELECT COUNT(*) FROM boleto_asiento WHERE orden_sube IS NULL");

        return $c;
    }

    /** @return array{salidas_legado: int, salidas_ruta_no_migrada: int} */
    private function rutasDeSalidasLegado(): array
    {
        $porLegado = $this->db->fetchAllKeyValue(
            "SELECT legacy_id, id FROM salida WHERE legacy_id IS NOT NULL AND ruta_id IS NULL",
        );
        $asignadas = 0;
        $sinRuta = 0;
        foreach (array_chunk(array_keys($porLegado), self::LOTE) as $lote) {
            $lista = implode(",", array_map("intval", $lote));
            foreach ($this->legado->filas(
                "SELECT s.id, i.ruta_codigo FROM salida s JOIN itineario i ON i.id = s.itinerario_id WHERE s.id IN ({$lista})",
            ) as $f) {
                $ruta = $this->rutas->deCodigo((string) $f["ruta_codigo"]);
                if ($ruta === null) {
                    $sinRuta++;
                    continue;
                }
                $asignadas += $this->db->executeStatement(
                    "UPDATE salida SET ruta_id = :r WHERE id = :id",
                    ["r" => $ruta->id, "id" => $porLegado[(string) $f["id"]] ?? $porLegado[(int) $f["id"]]],
                );
            }
        }

        return ["salidas_legado" => $asignadas, "salidas_ruta_no_migrada" => $sinRuta];
    }

    /**
     * Las filas de `$tabla` (salida o esquema) sin ruta toman la ruta con sus
     * mismas puntas que más salidas tiene (`RutaDeTrayecto`); si no hay, se
     * cuentan y quedan sin ruta.
     *
     * @return array<string, int>
     */
    private function rutasPorPuntas(string $tabla): array
    {
        $con = 0;
        $sin = 0;
        foreach ($this->db->fetchFirstColumn("SELECT DISTINCT trayecto_id FROM {$tabla} WHERE ruta_id IS NULL") as $trayectoId) {
            $ruta = $this->rutaDeTrayecto->para($this->em->find(Trayecto::class, (int) $trayectoId));
            if ($ruta === null) {
                $sin++;
                continue;
            }
            $con++;
            $this->db->executeStatement(
                "UPDATE {$tabla} SET ruta_id = :r WHERE trayecto_id = :t AND ruta_id IS NULL",
                ["r" => $ruta->getId(), "t" => $trayectoId],
            );
        }

        return ["{$tabla}_por_puntas" => $con, "{$tabla}_trayecto_sin_ruta" => $sin];
    }
}
