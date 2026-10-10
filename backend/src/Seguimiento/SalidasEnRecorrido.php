<?php

declare(strict_types=1);

namespace App\Seguimiento;

use App\Entity\BoletoAsiento;
use App\Entity\Enclave;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Venta\Itinerarios;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Salidas del modelo nuevo que ya salieron y sus paradas (enclaves en el orden
 * del itinerario del trayecto), listas para trazar la ruta.
 */
class SalidasEnRecorrido
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Itinerarios $itinerarios,
    ) {}

    /**
     * Salidas con fecha desde `$desde` hasta `$ahora` (hora local).
     * El criterio es el horario, no el estado: el personal casi nunca marca "iniciada".
     * Salió toda salida que pasó su hora, no está cancelada ni finalizada y tiene al menos
     * un boleto vigente (emitido, chequeado o en tránsito); las marcadas "iniciada" salen
     * aunque no tengan boletos (y hasta `$adelanto` antes de su hora). Quien llama
     * descarta las que ya llegaron.
     *
     * @return list<array{salida: Salida, kilometros: float, paradas: list<array<string, mixed>>}>
     */
    public function buscar(\DateTimeInterface $desde, \DateTimeInterface $ahora, \DateTimeInterface $adelanto): array
    {
        $qb = $this->em->createQueryBuilder();
        $vigente = $this->em->createQueryBuilder()
            ->select('1')
            ->from(BoletoAsiento::class, 'bo')
            ->where('bo.salida = s')
            ->andWhere('bo.estado IN (:vigentes)')
            ->getDQL();

        $salidas = $qb->select('s', 't', 'b', 'e')
            ->from(Salida::class, 's')
            ->join('s.trayecto', 't')
            ->join('s.bus', 'b')
            ->leftJoin('s.empresa', 'e')
            ->where('s.fecha >= :desde')
            ->andWhere($qb->expr()->orX(
                's.estado = :iniciada AND s.fecha <= :adelanto',
                's.estado IN (:programadas) AND s.fecha <= :ahora AND EXISTS (' . $vigente . ')',
            ))
            ->setParameter('desde', \DateTime::createFromInterface($desde))
            ->setParameter('ahora', \DateTime::createFromInterface($ahora))
            ->setParameter('adelanto', \DateTime::createFromInterface($adelanto))
            ->setParameter('iniciada', EstadoSalida::INICIADA)
            ->setParameter('programadas', [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO])
            ->setParameter('vigentes', [EstadoBoletoAsiento::EMITIDO, EstadoBoletoAsiento::CHEQUEADO, EstadoBoletoAsiento::TRANSITO])
            ->orderBy('s.fecha')
            ->getQuery()
            ->getResult();
        if ([] === $salidas) {
            return [];
        }

        $itinerarios = $this->itinerarios->deTrayectos(array_map(static fn(Salida $s) => $s->getTrayecto(), $salidas));
        $ids = [];
        foreach ($itinerarios as $it) {
            array_push($ids, ...$it->paradas);
        }
        $enclaves = [];
        foreach ($this->em->getRepository(Enclave::class)->findBy(['id' => array_values(array_unique($ids))]) as $enclave) {
            $enclaves[$enclave->getId()] = [
                'id' => $enclave->getId(),
                'nombre' => trim((string) $enclave->getNombre()),
                'latitude' => $enclave->getLatitud(),
                'longitude' => $enclave->getLongitud(),
                'departamento' => $enclave->getDepartamento(),
            ];
        }

        $out = [];
        foreach ($salidas as $s) {
            $paradas = [];
            foreach ($itinerarios[$s->getTrayecto()->getId()]->paradas as $id) {
                if (isset($enclaves[$id])) {
                    $paradas[] = $enclaves[$id];
                }
            }
            $out[] = ['salida' => $s, 'kilometros' => (float) $s->getTrayecto()->getDistanciaKm(), 'paradas' => $paradas];
        }

        return $out;
    }
}
