<?php

declare(strict_types=1);

namespace App\Croquis;

use App\Entity\Asiento;
use App\Entity\BoletoAsiento;
use App\Entity\Bus;
use App\Entity\BusSenal;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\TipoBusSenal;
use App\Entity\Salida;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lectura y reemplazo del croquis de un bus (asientos + señales).
 *
 * Al guardar, los asientos se reconocen por `id`: moverlos o renumerarlos
 * conserva el registro (y los boletos que lo referencian). Un asiento que
 * desaparece del croquis se borra, salvo que tenga boletos vendidos.
 *
 * Cambiar la distribución de un bus asignado a una salida activa no se
 * permite; al cambiarla, el bus pasa al molde que le corresponde (ADR-027).
 */
final class CroquisBus
{
    /** Estados en los que una salida tiene el bus comprometido. */
    public const SALIDA_ACTIVA = [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO, EstadoSalida::INICIADA];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Moldes $moldes,
    ) {}

    /**
     * `{ elementos: [...] }` en orden de lectura; cada asiento lleva
     * `conBoletos` (tiene boletos vendidos: no se puede quitar).
     *
     * @return array{elementos: list<array<string, mixed>>}
     */
    public function leer(Bus $bus): array
    {
        $elementos = Croquis::ordenar([
            ...array_map(
                ElementoCroquis::deAsiento(...),
                $bus->getAsientos()->toArray(),
            ),
            ...array_map(
                ElementoCroquis::deSenal(...),
                $bus->getSenales()->toArray(),
            ),
        ]);

        $vendidos = array_flip(
            $this->asientosConBoletos(
                array_values(
                    array_filter(
                        array_map(
                            static fn(ElementoCroquis $e) => $e->esAsiento()
                                ? $e->id
                                : null,
                            $elementos,
                        ),
                    ),
                ),
            ),
        );

        return [
            "elementos" => array_map(static function (ElementoCroquis $e) use (
                $vendidos,
            ) {
                $data = $e->toArray();
                if ($e->esAsiento()) {
                    $data["conBoletos"] = isset($vendidos[$e->id]);
                }

                return $data;
            }, $elementos),
        ];
    }

    /**
     * Reemplaza el croquis del bus por `$elementos` (ya validados con
     * `Croquis::desdeArray`).
     *
     * @param list<ElementoCroquis> $elementos
     *
     * @throws InvalidCroquis si un id no es de este bus, se quita un asiento con
     *                        boletos o cambia la distribución de un bus con salidas activas
     */
    public function guardar(Bus $bus, array $elementos): void
    {
        $actual = [
            ...array_map(ElementoCroquis::deAsiento(...), $bus->getAsientos()->toArray()),
            ...array_map(ElementoCroquis::deSenal(...), $bus->getSenales()->toArray()),
        ];
        if (Moldes::firma($actual) !== Moldes::firma($elementos) && ($activas = $this->salidasActivas($bus)) > 0) {
            throw new InvalidCroquis(sprintf(
                $activas === 1
                    ? "El bus está asignado a %d salida activa: no se puede cambiar su croquis hasta que termine o se le asigne otro bus."
                    : "El bus está asignado a %d salidas activas: no se puede cambiar su croquis hasta que terminen o se les asigne otro bus.",
                $activas,
            ));
        }

        /** @var array<int, Asiento> $asientos */
        $asientos = [];
        foreach ($bus->getAsientos() as $asiento) {
            $asientos[$asiento->getId()] = $asiento;
        }
        /** @var array<int, BusSenal> $senales */
        $senales = [];
        foreach ($bus->getSenales() as $senal) {
            $senales[$senal->getId()] = $senal;
        }

        // Primero se valida todo; las entidades solo se tocan si el croquis entra entero.
        $quitados = $asientos;
        foreach ($elementos as $elemento) {
            if (!$elemento->esAsiento() || $elemento->id === null) {
                continue;
            }
            if (!isset($asientos[$elemento->id])) {
                throw new InvalidCroquis(
                    sprintf(
                        "El asiento %d no pertenece a este bus.",
                        $elemento->id,
                    ),
                );
            }
            unset($quitados[$elemento->id]);
        }
        $vendidos = $this->asientosConBoletos(array_keys($quitados));
        if ($vendidos !== []) {
            $numeros = array_map(
                static fn(int $id) => $quitados[$id]->getNumero(),
                $vendidos,
            );
            sort($numeros);
            throw new InvalidCroquis(
                sprintf(
                    count($numeros) === 1
                        ? "El asiento %s tiene boletos vendidos: no se puede quitar del croquis."
                        : "Los asientos %s tienen boletos vendidos: no se pueden quitar del croquis.",
                    implode(", ", $numeros),
                ),
            );
        }

        foreach ($elementos as $elemento) {
            if ($elemento->esAsiento()) {
                $asiento = $elemento->id !== null ? $asientos[$elemento->id] : null;
                if ($asiento === null) {
                    $asiento = new Asiento();
                    $bus->addAsiento($asiento);
                    $this->entityManager->persist($asiento);
                }
                $asiento
                    ->setNumero((int) $elemento->numero)
                    ->setClase($elemento->clase)
                    ->setPlanta($elemento->planta)
                    ->setFila($elemento->fila)
                    ->setColumna($elemento->columna);
                continue;
            }

            $tipo = TipoBusSenal::from($elemento->tipo);
            $senal =
                $elemento->id !== null ? $senales[$elemento->id] ?? null : null;
            if ($senal !== null && $senal->getTipo() === $tipo) {
                unset($senales[$elemento->id]);
                $senal->mover(
                    $elemento->planta,
                    $elemento->fila,
                    $elemento->columna,
                );
                continue;
            }
            $bus->addSenal(
                new BusSenal(
                    $bus,
                    $tipo,
                    $elemento->planta,
                    $elemento->fila,
                    $elemento->columna,
                ),
            );
        }

        foreach ($quitados as $asiento) {
            $bus->getAsientos()->removeElement($asiento);
            $this->entityManager->remove($asiento);
        }
        // Las señales que quedan ya no están en el croquis (orphanRemoval).
        foreach ($senales as $senal) {
            $bus->removeSenal($senal);
        }

        $this->entityManager->flush();
        $this->moldes->asociar([(int) $bus->getId()]);
    }

    /**
     * Salidas programadas, abordando o iniciadas con este bus, desde hace un
     * día en adelante: las más viejas en esos estados quedaron así sin que
     * nadie las cerrara (muchas, del legado) y ya no comprometen al bus.
     */
    private function salidasActivas(Bus $bus): int
    {
        if ($bus->getId() === null) {
            return 0;
        }

        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select("COUNT(s.id)")
            ->from(Salida::class, "s")
            ->where("s.bus = :bus")
            ->andWhere("s.estado IN (:activas)")
            ->andWhere("s.fecha >= :desde")
            ->setParameter("bus", $bus)
            ->setParameter("desde", new \DateTime("-1 day"))
            ->setParameter("activas", array_map(static fn(EstadoSalida $e) => $e->value, self::SALIDA_ACTIVA))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Ids (de entre `$ids`) de los asientos con algún boleto.
     *
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function asientosConBoletos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map(
            "intval",
            $this->entityManager
                ->createQueryBuilder()
                ->select("DISTINCT IDENTITY(b.asiento)")
                ->from(BoletoAsiento::class, "b")
                ->where("b.asiento IN (:ids)")
                ->setParameter("ids", $ids)
                ->getQuery()
                ->getSingleColumnResult(),
        );
    }
}
