<?php

declare(strict_types=1);

namespace App\Croquis;

use App\Entity\Asiento;
use App\Entity\Bus;
use App\Entity\BusSenal;
use App\Entity\Enum\AsientoClase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Croquis distintos de la flota, para usar uno como plantilla al crear o
 * editar un bus. Los buses con la misma distribución (`Croquis::firma`) se
 * agrupan en una plantilla, como los "tipos de bus" del legado.
 *
 * Tres consultas escalares (buses, asientos, señales) sin importar el tamaño
 * de la flota. Respeta el `TenantFilter` (se consulta a través de `Bus`).
 */
final class PlantillasCroquis
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return list<array{
     *     firma: string,
     *     buses: list<array{id: int, codigo: string}>,
     *     asientos: int,
     *     plantas: int,
     *     elementos: list<array<string, mixed>>
     * }>
     */
    public function listar(): array
    {
        $porBus = [];
        foreach ($this->filas(Asiento::class, "a.numero, a.clase") as $fila) {
            $porBus[(int) $fila["bus"]][] = new ElementoCroquis(
                ElementoCroquis::ASIENTO,
                (int) $fila["planta"],
                (int) $fila["fila"],
                (int) $fila["columna"],
                (int) $fila["numero"],
                $fila["clase"] instanceof AsientoClase
                    ? $fila["clase"]
                    : AsientoClase::from((string) $fila["clase"]),
            );
        }
        foreach ($this->filas(BusSenal::class, "a.tipo") as $fila) {
            $tipo = $fila["tipo"];
            $porBus[(int) $fila["bus"]][] = new ElementoCroquis(
                $tipo instanceof \BackedEnum ? (string) $tipo->value : (string) $tipo,
                (int) $fila["planta"],
                (int) $fila["fila"],
                (int) $fila["columna"],
            );
        }

        $codigos = [];
        foreach (
            $this->entityManager
                ->createQueryBuilder()
                ->select("b.id, b.codigo")
                ->from(Bus::class, "b")
                ->getQuery()
                ->getArrayResult()
            as $bus
        ) {
            $codigos[(int) $bus["id"]] = (string) $bus["codigo"];
        }

        $plantillas = [];
        foreach ($porBus as $busId => $elementos) {
            if (!isset($codigos[$busId])) {
                continue;
            }
            $firma = Croquis::firma($elementos);
            $plantillas[$firma] ??= [
                "firma" => $firma,
                "buses" => [],
                "asientos" => count(
                    array_filter($elementos, static fn(ElementoCroquis $e) => $e->esAsiento()),
                ),
                "plantas" => max(
                    array_map(static fn(ElementoCroquis $e) => $e->planta, $elementos),
                ),
                "elementos" => array_map(
                    static fn(ElementoCroquis $e) => $e->toArray(),
                    Croquis::ordenar($elementos),
                ),
            ];
            $plantillas[$firma]["buses"][] = [
                "id" => $busId,
                "codigo" => $codigos[$busId],
            ];
        }

        $plantillas = array_values($plantillas);
        foreach ($plantillas as &$plantilla) {
            usort(
                $plantilla["buses"],
                static fn(array $a, array $b) => strnatcmp($a["codigo"], $b["codigo"]),
            );
        }
        unset($plantilla);
        // Las más usadas primero; a igualdad, por cantidad de asientos.
        usort(
            $plantillas,
            static fn(array $a, array $b) => [count($b["buses"]), $b["asientos"]] <=>
                [count($a["buses"]), $a["asientos"]],
        );

        return $plantillas;
    }

    /**
     * @param class-string $clase `Asiento` o `BusSenal`
     *
     * @return list<array<string, mixed>>
     */
    private function filas(string $clase, string $campos): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select(
                "IDENTITY(a.bus) AS bus, a.planta, a.fila, a.columna, {$campos}",
            )
            ->from($clase, "a")
            ->join("a.bus", "b")
            ->getQuery()
            ->getArrayResult();
    }
}
