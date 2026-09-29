<?php

declare(strict_types=1);

namespace App\Venta\Pago;

use Money\Money;

/**
 * Puerto hacia la pasarela de pago con tarjeta (Visa/Mastercard, 3-D Secure).
 * La implementación activa se elige en `config/services.yaml`
 * (`PasarelaSimulada` hasta integrar el banco adquirente).
 */
interface PasarelaPago
{
    public function cobrar(SolicitudPago $solicitud): ResultadoPago;

    /**
     * Completa el cobro después del desafío 3-D Secure con los datos que el
     * banco envió a la URL de retorno.
     *
     * @param array<string, mixed> $datosRetorno
     */
    public function confirmarAutenticacion(string $referenciaPasarela, array $datosRetorno): ResultadoPago;

    /** Devuelve un cobro aprobado (p. ej. si el asiento se perdió después de cobrar). */
    public function reembolsar(string $referenciaPasarela, Money $monto): void;
}
