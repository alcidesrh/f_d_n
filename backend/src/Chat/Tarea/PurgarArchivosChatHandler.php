<?php

declare(strict_types=1);

namespace App\Chat\Tarea;

use App\Chat\Archivos;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgarArchivosChatHandler
{
    public function __construct(
        private readonly Archivos $archivos,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{borrados: int} */
    public function __invoke(PurgarArchivosChat $mensaje): array
    {
        return ["borrados" => $this->archivos->purgarSueltos(\DateTimeImmutable::createFromInterface($this->reloj->now())->modify(PurgarArchivosChat::ANTIGUEDAD))];
    }
}
