<?php

// src/EventListener/ExceptionListener.php
namespace App\EventListener;

use App\Services\ServerSentEvent;
use Doctrine\DBAL\Driver\SQLSrv\Exception\Error;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExceptionListener {

  public function __construct(private ServerSentEvent $serverSentEvent, private TranslatorInterface $translatorInterface) {
  }
  public function __invoke(ExceptionEvent $event): void {
    // You get the exception object from the received event
    $exception = $event->getThrowable();

    do {
      if ($exception instanceof Error) {
        $message = sprintf(
          $this->translatorInterface->trans(
            'Error no se pudo conectar a la base de datos:'
          ) . ' %s with code: %s',
          $exception->getMessage(),
          $exception->getCode()
        );

        $this->serverSentEvent->errorGenerico($message);

        $response = new Response();
        // $response->setR$event->setResponse($response);
        return;
      }
      if (null == $exception->getPrevious()) {
        $stop = 1;
      }
    } while (
      null !== $exception = $exception->getPrevious()
    );
    return;
    // Customize your response object to display the exception details
    $response = new Response();
    $response->setContent($message ?? '');

    // HttpExceptionInterface is a special type of exception that
    // holds status code and header details
    if ($exception instanceof HttpExceptionInterface) {
      $response->setStatusCode($exception->getStatusCode());
      $response->headers->replace($exception->getHeaders());
    } else {
      $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    // sends the modified response object to the event
    $event->setResponse($response);
  }
}
