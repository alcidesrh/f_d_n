<?php

declare(strict_types=1);

namespace App\Salida\Horizonte;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ExtenderHorizonteHandler
{
    public function __construct(private readonly HorizonteSalidas $horizonte) {}

    /** @return array<string, mixed> */
    public function __invoke(ExtenderHorizonte $mensaje): array
    {
        return $mensaje->horas === null
            ? $this->horizonte->extender($mensaje->esquemaId)
            : $this->horizonte->rellenar($mensaje->esquemaId, $mensaje->horas);
    }
}
