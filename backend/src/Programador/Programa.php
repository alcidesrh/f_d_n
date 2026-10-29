<?php

declare(strict_types=1);

namespace App\Programador;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * El horario del programador de tareas (ADR-044): una entrada por cada
 * `Tarea` del catálogo. Lo consume solo el contenedor `programador`
 * (`messenger:consume scheduler_fdn`), que no ejecuta nada: reenvía cada
 * mensaje a la cola `tareas`, donde lo corre el `worker`.
 *
 * - Un cerrojo (`programador`) impide dos programadores a la vez.
 * - Con estado (`cache.programador`, en PostgreSQL): si estuvo detenido,
 *   al volver corre una vez lo que se perdió (no todas las veces).
 */
#[AsSchedule(self::NOMBRE)]
final class Programa implements ScheduleProviderInterface
{
    public const NOMBRE = 'fdn';

    private ?Schedule $schedule = null;

    public function __construct(
        #[Autowire(service: 'cache.programador')] private readonly CacheInterface $estado,
        private readonly LockFactory $cerrojos,
        #[Autowire(env: 'PROGRAMADOR_HORA_REPORTES')] private readonly string $horaReportes,
    ) {}

    public function getSchedule(): Schedule
    {
        if ($this->schedule !== null) {
            return $this->schedule;
        }
        $schedule = (new Schedule())
            ->stateful($this->estado)
            ->processOnlyLastMissedRun(true)
            ->lock($this->cerrojos->createLock('programador'));
        foreach (Tarea::cases() as $tarea) {
            $schedule->add(self::recurrente($tarea, $this->horaReportes));
        }

        return $this->schedule = $schedule;
    }

    public static function recurrente(Tarea $tarea, string $horaReportes): RecurringMessage
    {
        $mensaje = new RedispatchMessage($tarea->mensaje(), Tarea::COLA_TAREAS);
        $hora = $tarea->hora($horaReportes);

        return RecurringMessage::every($tarea->frecuencia(), $mensaje, $hora === null ? null : self::hoyA($hora));
    }

    /** Hoy a esa hora: el ancla de una tarea diaria (si ya pasó, la próxima es mañana). */
    private static function hoyA(string $hora): \DateTimeImmutable
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora, $m) !== 1) {
            throw new \InvalidArgumentException(sprintf('Hora de tarea inválida «%s»: use HH:MM.', $hora));
        }

        return (new \DateTimeImmutable('today'))->setTime((int) $m[1], (int) $m[2]);
    }
}
