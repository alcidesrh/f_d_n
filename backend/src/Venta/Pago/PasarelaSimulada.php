<?php

declare(strict_types=1);

namespace App\Venta\Pago;

use Money\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Pasarela de desarrollo (sin red), con las tarjetas de prueba habituales:
 *
 * - `4000 0000 0000 0002` → rechazada ("fondos insuficientes");
 * - `4000 0000 0000 3220` → pide 3-D Secure; el simulador de ACS
 *   (`/api/publico/pagos/simulador-3ds`) aprueba o rechaza;
 * - cualquier otra Visa/Mastercard válida → aprobada.
 */
final class PasarelaSimulada implements PasarelaPago
{
    public const URL_ACS = "/api/publico/pagos/simulador-3ds";

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function cobrar(SolicitudPago $solicitud, ?Continuacion $continuacion = null): ResultadoPago
    {
        if ($continuacion !== null) {
            $ref = $continuacion->estado["referencia"] ?? "";

            return ($continuacion->datos["resultado"] ?? "") === "Y"
                ? ResultadoPago::aprobado($ref, (string) random_int(100000, 999999))
                : ResultadoPago::rechazado("La autenticación 3-D Secure con su banco no se completó.", $ref);
        }

        $ref = "SIM-" . strtoupper(substr(Uuid::v4()->toBase58(), 0, 12));

        return match ($solicitud->tarjeta->numero()) {
            "4000000000000002" => ResultadoPago::rechazado("El banco rechazó la transacción: fondos insuficientes.", $ref),
            "4000000000003220" => ResultadoPago::autenticacion(
                $ref,
                self::URL_ACS,
                ["referencia" => $ref, "retorno" => $solicitud->urlRetorno],
                ["referencia" => $ref],
                "390px",
                "400px",
            ),
            default => ResultadoPago::aprobado($ref, (string) random_int(100000, 999999)),
        };
    }

    public function reembolsar(string $referenciaPasarela, Money $monto, int $empresaId): void
    {
        $this->logger->notice("Reembolso simulado de {monto} {moneda} ({ref}).", [
            "monto" => $monto->getAmount(),
            "moneda" => $monto->getCurrency()->getCode(),
            "ref" => $referenciaPasarela,
        ]);
    }

    public function huella(int $empresaId, string $referencia): ?array
    {
        return null;
    }
}
