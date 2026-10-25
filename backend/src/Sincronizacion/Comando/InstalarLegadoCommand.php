<?php

declare(strict_types=1);

namespace App\Sincronizacion\Comando;

use App\Sincronizacion\Legado\InstaladorLegado;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Instala (o quita) en el SQL Server del legado las tablas `sync_*` y los
 * triggers que anotan sus cambios (ADR-042). Se puede repetir. Solo anotan:
 * no cambian nada de lo que hace el legado.
 */
#[AsCommand(name: "app:sincronizacion:instalar-legado", description: "Instala o quita en el legado las tablas y triggers de la sincronización")]
final class InstalarLegadoCommand
{
    public function __construct(private readonly InstaladorLegado $instalador) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "Quitar en vez de instalar")] bool $desinstalar = false,
        #[Option(description: "Si FDN no responde al apartar, el legado: permitir (vende igual; defecto) o bloquear (no vende)")] ?string $siFalla = null,
    ): int {
        if ($siFalla !== null) {
            $this->instalador->siFalla($siFalla);
            $io->success("Si FDN no responde, el legado ahora va a: {$siFalla}.");

            return 0;
        }
        if ($desinstalar) {
            $this->instalador->desinstalar();
            $io->success("Tablas y triggers de la sincronización quitados del legado.");
        } else {
            $this->instalador->instalar();
            $io->success("Tablas y triggers de la sincronización instalados en el legado (el interruptor sigue como estaba).");
        }

        return 0;
    }
}
