<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Excepcion\Rechazo;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Un `Rechazo` que sale del controlador se responde como `{ error, codigo,
 * ...detalle }` con su estado HTTP, igual que lo hacía cada controlador. Antes
 * que el registro de errores de Symfony: es una respuesta prevista, no un fallo.
 */
#[AsEventListener(KernelEvents::EXCEPTION, priority: 10)]
final class RechazoComoJson
{
    public function __construct(private readonly SerializerInterface $serializer) {}

    public function __invoke(ExceptionEvent $evento): void
    {
        $rechazo = $evento->getThrowable();
        if (!$rechazo instanceof Rechazo) {
            return;
        }
        $json = $this->serializer->serialize($rechazo->toArray(), "json", ["json_encode_options" => JsonResponse::DEFAULT_ENCODING_OPTIONS]);
        $evento->allowCustomResponseCode();
        $evento->setResponse(new JsonResponse($json, $rechazo->estadoHttp, [], true));
    }
}
