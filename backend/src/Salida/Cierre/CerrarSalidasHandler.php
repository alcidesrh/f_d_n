<?php

declare(strict_types=1);

namespace App\Salida\Cierre;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CerrarSalidasHandler
{
    public function __construct(private readonly CierreSalidas $cierre) {}

    /** @return array{finalizadas: int, iniciadas: int, boletos: int, encomiendas: int} */
    public function __invoke(CerrarSalidas $mensaje): array
    {
        return $this->cierre->cerrar();
    }
}
