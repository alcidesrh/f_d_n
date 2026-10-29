<?php

declare(strict_types=1);

namespace App\Command;

use App\Venta\Tarea\PurgaVenta;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Una pasada de la limpieza de la venta (`App\Venta\Tarea\PurgaVenta`). El
 * programador de tareas la corre cada minuto (ADR-044): esto es para hacerlo
 * a mano.
 */
#[AsCommand(name: "app:venta:purgar", description: "Borra reservas web vencidas y ventas pendientes abandonadas")]
final class VentaPurgarCommand
{
    public function __construct(private readonly PurgaVenta $purga) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "Antigüedad mínima de una venta pendiente para borrarla")] int $pendientesMinutos = PurgaVenta::PENDIENTES_MINUTOS,
    ): int {
        $r = $this->purga->purgar($pendientesMinutos);
        $io->writeln(sprintf("reservas vencidas: %d, ventas pendientes abandonadas: %d", $r["reservas"], $r["ventas"]));

        return 0;
    }
}
