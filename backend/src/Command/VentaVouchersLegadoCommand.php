<?php

declare(strict_types=1);

namespace App\Command;

use App\Migration\Migrador;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Marca como voucher las ventas de boletos del legado emitidos con voucher
 * (el croquis de taquilla los pinta aparte). La migración completa ya lo
 * hace; esto es para bases migradas antes.
 */
#[AsCommand(name: "app:venta:vouchers-legado", description: "Marca las ventas migradas que en el legado fueron voucher")]
final class VentaVouchersLegadoCommand
{
    public function __construct(private readonly Migrador $migrador) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(sprintf("Ventas marcadas como voucher: %d", $this->migrador->actualizarVouchers()));

        return 0;
    }
}
