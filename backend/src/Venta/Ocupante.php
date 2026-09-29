<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Enum\CanalVenta;

/**
 * Algo que aparta un asiento de un recorrido para un trayecto: un boleto
 * vendido (o en venta pendiente de factura) o una reserva web vigente.
 */
final readonly class Ocupante
{
    public const VENDIDO = "vendido";
    public const RESERVADO = "reservado";

    public function __construct(
        public int $asientoId,
        public int $trayectoId,
        public string $tipo,
        public ?CanalVenta $canal = null,
        /** Token del carrito, en las reservas. */
        public ?string $token = null,
    ) {}
}
