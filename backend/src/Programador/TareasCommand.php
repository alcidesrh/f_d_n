<?php

declare(strict_types=1);

namespace App\Programador;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Estado de las tareas del programador (ADR-044) y, con `--ejecutar=`, una
 * en la cola `tareas` ahora mismo (la corre el worker, con su cerrojo).
 */
#[AsCommand(name: 'app:programador:tareas', description: 'Estado de las tareas programadas; --ejecutar=<tarea> la pone en cola ya')]
final class TareasCommand
{
    public function __construct(
        private readonly RegistroTareas $registro,
        private readonly EjecucionManual $ejecucion,
        private readonly ClockInterface $reloj,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Código de la tarea que se pone en cola ahora')] ?string $ejecutar = null,
    ): int {
        if ($ejecutar !== null) {
            $tarea = Tarea::tryFrom($ejecutar);
            if ($tarea === null) {
                $io->error(sprintf('No existe la tarea «%s». Use una de: %s.', $ejecutar, implode(', ', array_column(Tarea::cases(), 'value'))));

                return 1;
            }
            $this->ejecucion->encolar($tarea);
            $io->success(sprintf('«%s» quedó en la cola; la corre el worker.', $tarea->nombre()));

            return 0;
        }

        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());
        $io->table(
            ['Tarea', 'Cada', 'Último inicio', 'Última buena', 'Resultado', 'ms', 'Detenida'],
            array_map(static fn(EstadoDeTarea $e) => [
                $e->tarea->value,
                $e->tarea->frecuencia(),
                $e->ultimoInicio?->format('d/m H:i:s') ?? '—',
                $e->ultimoExito?->format('d/m H:i:s') ?? '—',
                $e->resultado === null ? '—' : $e->resultado . ($e->error !== null ? ': ' . mb_substr($e->error, 0, 60) : ''),
                $e->duracionMs ?? '—',
                $e->detenida($ahora) ? 'sí' : 'no',
            ], $this->registro->todas()),
        );
        $fallidos = $this->registro->fallidos();
        if ($fallidos > 0) {
            $io->warning(sprintf('%d mensajes agotaron sus reintentos (messenger:failed:show).', $fallidos));
        }

        return 0;
    }
}
