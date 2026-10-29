<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SincronizarLegadoHandler
{
    public function __construct(private readonly Ciclo $ciclo) {}

    /** @return array<string, mixed> */
    public function __invoke(SincronizarLegado $mensaje): array
    {
        return $this->ciclo->correr() ?? ["apagada" => true];
    }
}
