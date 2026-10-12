<?php

declare(strict_types=1);

namespace App\Command;

use App\Migration\Migrador;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Corrige los boletos del legado en estado "Cancelado" (no se presentó a
 * abordar) que se migraron como anulados (ADR-032). La migración completa
 * ya los trae como `cancelado`; esto es para bases migradas antes.
 */
#[AsCommand(name: "app:venta:cancelados-legado", description: "Pasa a cancelado los boletos migrados como anulados que en el legado son cancelados")]
final class VentaCanceladosLegadoCommand
{
    public function __construct(private readonly Migrador $migrador) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(sprintf("Boletos corregidos a cancelado: %d", $this->migrador->actualizarCancelados()));

        return 0;
    }
}
