<?php

declare(strict_types=1);

namespace App\Sincronizacion\Comando;

use App\Sincronizacion\Ciclo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Una vuelta del ciclo de la sincronización con el legado (ADR-042). El
 * programador de tareas la corre cada minuto (ADR-044): esto es para
 * hacerlo a mano. Con el interruptor apagado no hace nada.
 */
#[AsCommand(name: "app:sincronizar", description: "Sincroniza con el sistema anterior: trae sus cambios y lleva los de aquí")]
final class SincronizarCommand
{
    public function __construct(private readonly Ciclo $ciclo) {}

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $resumen = $this->ciclo->correr();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return 1;
        }
        $io->writeln($resumen === null ? "La sincronización está apagada." : (string) json_encode($resumen, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
