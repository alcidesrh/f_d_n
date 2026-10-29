<?php

declare(strict_types=1);

namespace App\Venta\Tarea;

use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Venta\PublicadorOcupacion;
use App\Venta\Reservas;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Limpieza de la venta (ADR-021). La disponibilidad ya ignora las reservas
 * vencidas, así que esto no libera asientos antes: solo borra filas muertas.
 *
 * - Reservas web vencidas (la "precompra" abandonada).
 * - Ventas de taquilla que quedaron `pendientes` de factura porque el
 *   proceso murió a mitad (más de `PENDIENTES_MINUTOS`).
 *
 * La corre el programador cada minuto (`Tarea::PURGAR_VENTA`, ADR-044) y,
 * a mano, `app:venta:purgar`.
 */
final class PurgaVenta
{
    public const PENDIENTES_MINUTOS = 10;

    public function __construct(
        private readonly Reservas $reservas,
        private readonly EntityManagerInterface $em,
        private readonly PublicadorOcupacion $publicador,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{reservas: int, ventas: int} lo que borró */
    public function purgar(int $pendientesMinutos = self::PENDIENTES_MINUTOS): array
    {
        return ["reservas" => $this->reservas->purgar(), "ventas" => $this->purgarPendientes($pendientesMinutos)];
    }

    private function purgarPendientes(int $minutos): int
    {
        $limite = \DateTime::createFromImmutable($this->reloj->now()->modify("-{$minutos} minutes"));
        $ventas = $this->em->getRepository(BoletoVenta::class)->createQueryBuilder("v")
            ->where("v.estado = :pendiente")
            ->andWhere("v.createdAt < :limite")
            ->setParameter("pendiente", EstadoBoletoVenta::PENDIENTE)
            ->setParameter("limite", $limite)
            ->getQuery()
            ->getResult();

        $salidas = [];
        foreach ($ventas as $venta) {
            foreach ($venta->getAsientos() as $b) {
                $salidas[$b->getSalida()->getId()] = true;
            }
            $this->em->remove($venta);
        }
        $this->em->flush();
        foreach (array_keys($salidas) as $id) {
            $this->publicador->cambio($id);
        }

        return count($ventas);
    }
}
