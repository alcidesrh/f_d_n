<?php

declare(strict_types=1);

namespace App\Salida\Horizonte;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ExtenderHorizontesHandler
{
    public function __construct(
        private readonly HorizonteSalidas $horizonte,
        private readonly MessageBusInterface $bus,
    ) {}

    /** @return array{esquemas: int} */
    public function __invoke(ExtenderHorizontes $mensaje): array
    {
        $ids = $this->horizonte->pendientes();
        foreach ($ids as $id) {
            $this->bus->dispatch(new ExtenderHorizonte($id));
        }

        return ['esquemas' => count($ids)];
    }
}
