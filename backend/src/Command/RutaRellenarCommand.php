<?php

declare(strict_types=1);

namespace App\Command;

use App\Migration\Salida\RellenoRutas;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Completa una base migrada antes de ADR-030: rutas y paradas del legado
 * (solo lectura), ruta y horario de cada salida y tramo por orden de cada
 * boleto y reserva. Idempotente. Transitorio: se retira con `Subtrayecto`.
 */
#[AsCommand(name: "app:ruta:rellenar", description: "Rutas, horarios de salidas y tramos por orden en una base ya migrada (ADR-030)")]
final class RutaRellenarCommand
{
    public function __construct(
        private readonly RellenoRutas $relleno,
        private readonly Connection $db,
    ) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $c = $this->db->transactional(fn() => $this->relleno->rellenar(static fn(string $m) => $io->writeln($m)));
        $io->table(["", "cantidad"], array_map(null, array_keys($c), array_values($c)));
        $io->success("Rutas y horarios completos.");

        return 0;
    }
}
