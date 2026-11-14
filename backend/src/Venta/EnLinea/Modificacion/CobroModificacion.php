<?php

declare(strict_types=1);

namespace App\Venta\EnLinea\Modificacion;

use App\Venta\Pago\DireccionFacturacion;
use App\Venta\Pago\Navegador;
use App\Venta\Pago\Tarjeta;

/**
 * Datos del cobro de una modificación con costo: la tarjeta (nunca se
 * guarda; se reenvía en cada paso de 3-D Secure), su dirección y lo que
 * devolvió el banco en el paso anterior (`continuar`).
 */
final readonly class CobroModificacion
{
    /**
     * @param array<string, string>|null $continuar
     */
    public function __construct(
        public Tarjeta $tarjeta,
        public DireccionFacturacion $direccion,
        public Navegador $navegador,
        public string $urlRetorno,
        public ?array $continuar = null,
    ) {}
}
