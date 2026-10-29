<?php

declare(strict_types=1);

namespace App\Programador;

use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Envuelve la ejecución de cada tarea del programador en el worker (ADR-044):
 *
 * - Cerrojo por tarea: si la corrida anterior sigue (una sincronización que
 *   tarda más de un minuto), esta se omite en lugar de amontonarse.
 * - Latido: inicio, fin, duración, resumen y error en `estado_tarea`.
 * - Un fallo no se reintenta ni va a la cola `failed`: queda en el latido y
 *   la próxima corrida lo vuelve a intentar con el estado del momento. Así
 *   una tarea de cada minuto que falla no llena la cola de fallidos.
 *
 * Solo actúa al consumir (`ReceivedStamp`); al despachar no hace nada.
 */
final class TareasMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LockFactory $cerrojos,
        private readonly RegistroTareas $registro,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $mensaje = $envelope->getMessage();
        if (!$mensaje instanceof MensajeDeTarea || $envelope->last(ReceivedStamp::class) === null) {
            return $stack->next()->handle($envelope, $stack);
        }

        $tarea = $mensaje->tarea();
        $cerrojo = $this->cerrojos->createLock('tarea.' . $tarea->value, 3600.0);
        if (!$cerrojo->acquire(false)) {
            $this->registro->omitida($tarea);
            $this->logger->info('Tarea {tarea} omitida: la corrida anterior sigue.', ['tarea' => $tarea->value]);

            return $envelope;
        }

        $inicio = $this->registro->empezo($tarea);
        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $resumen = $envelope->last(HandledStamp::class)?->getResult();
            $this->registro->termino($tarea, $inicio, is_array($resumen) ? $resumen : null);

            return $envelope;
        } catch (\Throwable $e) {
            $causa = $e instanceof HandlerFailedException ? ($e->getWrappedExceptions()[0] ?? $e) : $e;
            $this->registro->fallo($tarea, $inicio, $causa);
            $this->logger->error('Tarea {tarea} falló: {error}', ['tarea' => $tarea->value, 'error' => $causa->getMessage(), 'exception' => $causa]);

            return $envelope;
        } finally {
            $cerrojo->release();
        }
    }
}
