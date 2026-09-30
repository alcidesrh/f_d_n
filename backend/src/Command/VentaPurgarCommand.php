<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Venta\PublicadorOcupacion;
use App\Venta\Reservas;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Limpieza de la venta (ADR-021). La disponibilidad ya ignora las reservas
 * vencidas, así que esto no libera asientos antes: solo borra filas muertas.
 *
 * - Reservas web vencidas (la "precompra" abandonada).
 * - Ventas de taquilla que quedaron `pendientes` de factura porque el
 *   proceso murió a mitad (más de `--pendientes-minutos`).
 *
 * `--cada=N` lo deja corriendo y repite cada N segundos (como el comando
 * permanente del legado); sin él, corre una vez (cron / systemd timer).
 */
#[AsCommand(name: "app:venta:purgar", description: "Borra reservas web vencidas y ventas pendientes abandonadas")]
final class VentaPurgarCommand
{
    public function __construct(
        private readonly Reservas $reservas,
        private readonly EntityManagerInterface $em,
        private readonly PublicadorOcupacion $publicador,
        private readonly ClockInterface $reloj,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "Repetir cada N segundos (0 = una sola vez)")] int $cada = 0,
        #[Option(description: "Antigüedad mínima de una venta pendiente para borrarla")] int $pendientesMinutos = 10,
    ): int {
        do {
            $reservas = $this->reservas->purgar();
            $ventas = $this->purgarPendientes($pendientesMinutos);
            if ($reservas + $ventas > 0 || $cada === 0) {
                $io->writeln(sprintf("[%s] reservas vencidas: %d, ventas pendientes abandonadas: %d", $this->reloj->now()->format("H:i:s"), $reservas, $ventas));
            }
            $this->em->clear();
            if ($cada > 0) {
                sleep($cada);
            }
        } while ($cada > 0);

        return 0;
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

        $recorridos = [];
        foreach ($ventas as $venta) {
            foreach ($venta->getAsientos() as $b) {
                $recorridos[$b->getRecorrido()->getId()] = true;
            }
            $this->em->remove($venta);
        }
        $this->em->flush();
        foreach (array_keys($recorridos) as $id) {
            $this->publicador->cambio($id);
        }

        return count($ventas);
    }
}
