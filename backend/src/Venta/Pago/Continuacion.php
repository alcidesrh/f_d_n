<?php

declare(strict_types=1);

namespace App\Venta\Pago;

/**
 * Siguiente paso de un cobro de varios pasos (recolección de datos del
 * dispositivo, desafío 3-D Secure). `estado` es lo que la pasarela devolvió
 * en el paso anterior (se guarda en el servidor, en `PagoWeb`); `datos` es lo
 * que envió el navegador (no confiable).
 */
final readonly class Continuacion
{
    /**
     * @param array<string, string> $estado
     * @param array<string, string> $datos
     */
    public function __construct(
        public array $estado,
        public array $datos = [],
    ) {}
}
