<?php

declare(strict_types=1);

namespace App\Venta\Pago;

use App\Venta\Pago\Cybersource\PasarelaCybersource;
use Money\Money;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Elige la pasarela según `PAGO_PASARELA`: `cybersource` (real) o `simulada`
 * (por defecto; desarrollo y pruebas). Es el servicio que ve `PasarelaPago`
 * (`config/services.yaml`).
 */
final class PasarelaActiva implements PasarelaPago
{
    public function __construct(
        #[Autowire(env: "default:pago_pasarela_defecto:PAGO_PASARELA")]
        private readonly string $pasarela,
        private readonly PasarelaSimulada $simulada,
        private readonly PasarelaCybersource $cybersource,
    ) {}

    public function cobrar(SolicitudPago $solicitud, ?Continuacion $continuacion = null): ResultadoPago
    {
        return $this->activa()->cobrar($solicitud, $continuacion);
    }

    public function reembolsar(string $referenciaPasarela, Money $monto, int $empresaId): void
    {
        $this->activa()->reembolsar($referenciaPasarela, $monto, $empresaId);
    }

    public function huella(int $empresaId, string $referencia): ?array
    {
        return $this->activa()->huella($empresaId, $referencia);
    }

    public function esSimulada(): bool
    {
        return $this->activa() === $this->simulada;
    }

    private function activa(): PasarelaPago
    {
        return strtolower(trim($this->pasarela)) === "cybersource" ? $this->cybersource : $this->simulada;
    }
}
