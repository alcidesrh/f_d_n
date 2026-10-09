<?php

declare(strict_types=1);

namespace App\Croquis;

use App\Entity\Croquis as Molde;
use App\Entity\Enum\AsientoClase;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Moldes de croquis (ADR-027): asocia cada bus con el `Croquis` de su
 * distribución, creándolo si todavía no existe. Un molde no se edita ni se
 * duplica (`firma` única); un bus que cambia de distribución pasa a otro.
 *
 * Trabaja con DBAL para servir igual a la API (tras guardar el croquis), a
 * la migración del legado (dentro de su transacción) y al relleno inicial
 * (`app:croquis:asociar`). No respeta el `TenantFilter`: no lo necesita.
 */
final class Moldes
{
    private const LOTE = 500;

    public function __construct(
        private readonly Connection $db,
    ) {}

    /**
     * Asocia los buses `$busIds` (todos si es null) con su molde. Un bus sin
     * asientos queda sin molde.
     *
     * @param list<int>|null $busIds
     *
     * @return int buses cuyo molde cambió
     */
    public function asociar(?array $busIds = null): int
    {
        $ids = $busIds ?? array_map("intval", $this->db->fetchFirstColumn("SELECT id FROM bus ORDER BY id"));
        $cambiados = 0;
        foreach (array_chunk(array_values(array_unique($ids)), self::LOTE) as $lote) {
            $elementos = $this->elementosPorBus($lote);
            $actuales = array_map(
                static fn($v) => $v === null ? null : (int) $v,
                array_column(
                    $this->db->fetchAllAssociative("SELECT id, croquis_id FROM bus WHERE id IN (:ids)", ["ids" => $lote], ["ids" => ArrayParameterType::INTEGER]),
                    "croquis_id",
                    "id",
                ),
            );
            foreach ($lote as $busId) {
                if (!array_key_exists($busId, $actuales)) {
                    continue;
                }
                $molde = $this->tieneAsientos($elementos[$busId] ?? []) ? $this->molde($elementos[$busId]) : null;
                if ($molde !== $actuales[$busId]) {
                    $this->db->executeStatement("UPDATE bus SET croquis_id = :c WHERE id = :id", ["c" => $molde, "id" => $busId]);
                    $cambiados++;
                }
            }
        }

        return $cambiados;
    }

    /**
     * Id del molde de esa distribución; lo crea si no existe.
     *
     * @param list<ElementoCroquis> $elementos
     */
    public function molde(array $elementos): int
    {
        $firma = Croquis::firma($elementos);
        $id = $this->db->fetchOne("SELECT id FROM croquis WHERE firma = :f", ["f" => $firma]);
        if ($id !== false) {
            return (int) $id;
        }

        $asientos = array_values(array_filter($elementos, static fn(ElementoCroquis $e) => $e->esAsiento()));
        $this->db->executeStatement(
            "INSERT INTO croquis (firma, asientos, asientos_b, plantas, elementos, creado_en)
             VALUES (:firma, :asientos, :b, :plantas, :elementos, NOW())
             ON CONFLICT (firma) DO NOTHING",
            [
                "firma" => $firma,
                "asientos" => count($asientos),
                "b" => count(array_filter($asientos, static fn(ElementoCroquis $e) => $e->clase === AsientoClase::B)),
                "plantas" => max(1, ...array_map(static fn(ElementoCroquis $e) => $e->planta, $elementos)),
                "elementos" => json_encode(self::sinIds($elementos), JSON_THROW_ON_ERROR),
            ],
        );

        return (int) $this->db->fetchOne("SELECT id FROM croquis WHERE firma = :f", ["f" => $firma]);
    }

    /**
     * Firma de una distribución, sin crear el molde.
     *
     * @param list<ElementoCroquis> $elementos
     */
    public static function firma(array $elementos): ?string
    {
        return array_filter($elementos, static fn(ElementoCroquis $e) => $e->esAsiento()) === [] ? null : Croquis::firma($elementos);
    }

    /**
     * Vista de un molde para elegirlo al crear una salida.
     *
     * @return array<string, mixed>
     */
    public static function vista(Molde $m): array
    {
        return [
            "id" => (int) $m->getId(),
            "asientos" => $m->getAsientos(),
            "asientosB" => $m->getAsientosB(),
            "plantas" => $m->getPlantas(),
            // Misma forma que el croquis de un bus (`ElementoCroquis::toArray`), sin ids.
            "elementos" => array_map(static fn(array $e) => ["id" => null] + $e, $m->getElementos()),
        ];
    }

    /**
     * Elementos (asientos y señales) de cada bus.
     *
     * @param list<int> $busIds
     *
     * @return array<int, list<ElementoCroquis>>
     */
    private function elementosPorBus(array $busIds): array
    {
        $porBus = [];
        $tipos = ["ids" => ArrayParameterType::INTEGER];
        foreach ($this->db->fetchAllAssociative(
            "SELECT bus_id, numero, clase, planta, fila, columna FROM asiento WHERE bus_id IN (:ids)",
            ["ids" => $busIds],
            $tipos,
        ) as $f) {
            $porBus[(int) $f["bus_id"]][] = new ElementoCroquis(
                ElementoCroquis::ASIENTO,
                (int) $f["planta"],
                (int) $f["fila"],
                (int) $f["columna"],
                (int) $f["numero"],
                AsientoClase::from((string) $f["clase"]),
            );
        }
        foreach ($this->db->fetchAllAssociative(
            "SELECT bus_id, tipo, planta, fila, columna FROM bus_senal WHERE bus_id IN (:ids)",
            ["ids" => $busIds],
            $tipos,
        ) as $f) {
            $porBus[(int) $f["bus_id"]][] = new ElementoCroquis((string) $f["tipo"], (int) $f["planta"], (int) $f["fila"], (int) $f["columna"]);
        }

        return $porBus;
    }

    /** @param list<ElementoCroquis> $elementos */
    private function tieneAsientos(array $elementos): bool
    {
        return self::firma($elementos) !== null;
    }

    /**
     * @param list<ElementoCroquis> $elementos
     *
     * @return list<array<string, int|string|null>>
     */
    private static function sinIds(array $elementos): array
    {
        return array_map(static function (ElementoCroquis $e) {
            $data = $e->toArray();
            unset($data["id"]);

            return $data;
        }, Croquis::ordenar($elementos));
    }
}
