<?php

declare(strict_types=1);

namespace App\Command;

use App\Migration\MigradorEstaticos;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Departamento de cada enclave desde el legado (`estacion.departamento_id`):
 * la página web agrupa orígenes y destinos por departamento. La migración
 * completa ya lo trae; esto es para bases migradas antes.
 */
#[AsCommand(name: "app:enclave:departamentos", description: "Copia del legado el departamento de cada enclave")]
final class EnclaveDepartamentosCommand
{
    public function __construct(private readonly MigradorEstaticos $migrador) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(sprintf("Enclaves actualizados: %d", $this->migrador->actualizarDepartamentos()));

        return 0;
    }
}
