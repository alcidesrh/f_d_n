<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Sincronizacion\Legado\ImportadorLegado;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BarrerSalidasLegadoHandler
{
    public function __construct(
        private readonly Interruptor $interruptor,
        private readonly ImportadorLegado $importador,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(BarrerSalidasLegado $mensaje): array
    {
        if (!$this->interruptor->activo()) {
            return ["apagada" => true];
        }
        $resumen = $this->importador->barrerSalidas();
        if ($resumen === null) {
            return ["ocupado" => true];
        }
        $this->interruptor->anotarBarrido($resumen);

        return $resumen;
    }
}
