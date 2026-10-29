<?php

declare(strict_types=1);

namespace App\Tablero\Tarea;

use App\Tablero\TableroEnCache;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RefrescarTableroHandler
{
    public function __construct(private readonly TableroEnCache $tablero) {}

    /** @return array{tableros: int} */
    public function __invoke(RefrescarTablero $mensaje): array
    {
        return ["tableros" => $this->tablero->refrescar()];
    }
}
