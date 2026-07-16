<?php

namespace App\Error;

use ApiPlatform\GraphQl\Error\ErrorHandlerInterface;
use App\Services\ServerSentEvent;
use GraphQL\Error\Error;

final class ErrorHandler implements ErrorHandlerInterface
{
    private $defaultErrorHandler;

    public function __construct(ErrorHandlerInterface $defaultErrorHandler, private ServerSentEvent $serverSentEvent)
    {
        $this->defaultErrorHandler = $defaultErrorHandler;
    }

    public function __invoke(array $errors, callable $formatter): array
    {

        $response = [];
        foreach ($errors as $key => $error) {
            if ($error instanceof GraphqlErrorHandler) {
                $response['message'] = $error->getMessage();
            } else if ($error instanceof Error) {
                $response['message'] = $error->getMessage();
                $response['caption'] = $error->getFile() . ' Line: ' . $error->getLine();
            } else {
                $response['message'] = $formatter($error);
            }
            $this->serverSentEvent->error($response);
        }
        // Log or filter the errors.

        return [[]];
        return ($this->defaultErrorHandler)($errors, $formatter);
    }
}
