<?php

declare(strict_types=1);

namespace App\Venta\Tarea;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgarVentaHandler
{
    public function __construct(private readonly PurgaVenta $purga) {}

    /** @return array{reservas: int, ventas: int} */
    public function __invoke(PurgarVenta $mensaje): array
    {
        return $this->purga->purgar();
    }
}
