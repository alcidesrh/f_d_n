<?php

declare(strict_types=1);

namespace App\Command;

use App\Migration\MigradorIAM;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Afina los accesos con el legado (ADR-039): roles con sus capacidades y,
 * por usuario, si sigue activo y una asignación por rol con las empresas y
 * estaciones que tenía en el legado. Es el paso «iam» de la migración, para
 * correrlo solo (p. ej. después de `Version20261022090000`, que solo pudo
 * inferir el alcance por las ventas). Respeta lo otorgado a mano.
 */
#[AsCommand(
    name: 'app:acceso:alcance-legado',
    description: 'Roles, alcance (empresas y estaciones) y usuarios activos desde el legado (ADR-039).',
)]
final class AlcanceLegadoCommand extends Command
{
    public function __construct(
        #[Autowire(lazy: true)] private readonly MigradorIAM $iam,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $c = $this->iam->migrar($output);
        $output->writeln(sprintf(
            '<info>Listo:</info> %d roles nuevos, %d usuarios, %d asignaciones, %d desactivados. Revise los que quedaron sin alcance en /configuracion/accesos.',
            $c['roles'],
            $c['usuarios'],
            $c['asignaciones'],
            $c['desactivados'],
        ));

        return Command::SUCCESS;
    }
}
