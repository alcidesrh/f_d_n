<?php

declare(strict_types=1);

namespace App\Command;

use App\Migration\ConsolidadorRoles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Consolida en puestos los roles del legado que ya puso la migración
 * (ADR-052). Sin `--aplicar` solo informa: hace todo en una transacción y la
 * deshace. Respeta lo otorgado a mano.
 */
#[AsCommand(
    name: 'app:acceso:consolidar-roles',
    description: 'Consolida en puestos los roles del legado de las asignaciones migradas (ADR-052). Sin --aplicar solo informa.',
)]
final class ConsolidarRolesCommand extends Command
{
    public function __construct(private readonly ConsolidadorRoles $consolidador)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('aplicar', null, InputOption::VALUE_NONE, 'Guarda los cambios (sin esto, solo muestra lo que haría)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $aplicar = (bool) $input->getOption('aplicar');
        $i = $this->consolidador->consolidar($aplicar);

        $io->title($aplicar ? 'Roles consolidados' : 'Consolidación de roles (simulación: no se guardó nada)');
        $io->text(sprintf('%d usuarios: %d asignaciones migradas → %d.', $i['usuarios'], $i['antes'], $i['despues']));

        $io->section('Asignaciones por puesto');
        $io->table(['Puesto', 'Asignaciones'], array_map(null, array_keys($i['puestos']), array_values($i['puestos'])));

        if ($i['creados'] !== [] || $i['renombrados'] !== [] || $i['capacidades'] !== []) {
            $io->section('Catálogo');
            foreach ($i['creados'] as $r) {
                $io->text("Nuevo: $r");
            }
            foreach ($i['renombrados'] as $de => $a) {
                $io->text("Renombrado: $de → $a");
            }
            foreach ($i['capacidades'] as $r => $d) {
                $io->text(sprintf('%s: %s%s', $r, $d['quita'] ? '− ' . implode(', ', $d['quita']) . ' ' : '', $d['agrega'] ? '+ ' . implode(', ', $d['agrega']) : ''));
            }
        }

        $io->section('Para revisar');
        $io->text(sprintf('Sin cortesía por ROLE_NO_VENDER_VOUCHER (%d): %s', count($i['sinCortesia']), implode(', ', $i['sinCortesia']) ?: '—'));
        $io->text(sprintf('Ganan solo lectura (%d usuarios):', count($i['ampliadas'])));
        foreach ($i['ampliadas'] as $u => $caps) {
            $io->text("  usuario $u: " . implode(', ', $caps));
        }
        $io->text(sprintf('Sin cubrir, otorgar a mano (%d usuarios):', count($i['sinCubrir'])));
        foreach ($i['sinCubrir'] as $u => $caps) {
            $io->text("  usuario $u: " . implode(', ', $caps));
        }
        $io->text(sprintf('Emisión de cortesías otorgada a quien la tenía a mano por un puesto que la pierde: %d', $i['cortesiaAMano']));
        $io->text(sprintf('Audiencias copiadas a su reemplazo: %d', $i['audiencias']));
        $io->text('Roles retirados eliminados: ' . (implode(', ', $i['eliminados']) ?: '—'));
        foreach ($i['conservados'] as $r => $n) {
            $io->text("Se conserva $r: aún tiene $n asignaciones otorgadas a mano");
        }

        $aplicar
            ? $io->success('Listo. Los accesos nuevos rigen desde la próxima petición de cada usuario.')
            : $io->note('Nada se guardó. Para aplicar: app:acceso:consolidar-roles --aplicar');

        return Command::SUCCESS;
    }
}
