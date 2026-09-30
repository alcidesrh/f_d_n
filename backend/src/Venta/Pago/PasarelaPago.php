<?php

declare(strict_types=1);

namespace App\Venta\Pago;

use Money\Money;

/**
 * Puerto hacia la pasarela de pago con tarjeta (Visa/Mastercard, 3-D Secure).
 * La implementación activa la elige `PasarelaActiva` según `PAGO_PASARELA`.
 *
 * Un cobro puede tener varios pasos: `cobrar()` sin continuación lo inicia; si
 * el resultado pide algo al navegador (`dispositivo` o `autenticacion`), la
 * página lo hace y se vuelve a llamar con la `Continuacion` (el `estado` del
 * resultado anterior + lo que envió el navegador) y la misma solicitud: los
 * datos de la tarjeta nunca se guardan entre pasos.
 */
interface PasarelaPago
{
    public function cobrar(SolicitudPago $solicitud, ?Continuacion $continuacion = null): ResultadoPago;

    /**
     * Devuelve un cobro aprobado (p. ej. si el asiento se perdió después de cobrar).
     *
     * @throws \RuntimeException si la pasarela no lo aceptó (hay que devolverlo a mano)
     */
    public function reembolsar(string $referenciaPasarela, Money $monto, int $empresaId): void;

    /**
     * Huella del dispositivo para el antifraude del banco: el script que la
     * página debe cargar antes de pagar, o null si no aplica.
     *
     * @return array{script: string}|null
     */
    public function huella(int $empresaId, string $referencia): ?array;
}
