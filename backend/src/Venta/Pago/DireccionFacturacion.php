<?php

declare(strict_types=1);

namespace App\Venta\Pago;

use App\Venta\Excepcion\VentaRechazada;

/**
 * Dirección de facturación de la tarjeta (la que el banco tiene registrada).
 * La pasarela la pide para el análisis de riesgo y 3-D Secure; no se guarda.
 */
final readonly class DireccionFacturacion
{
    private function __construct(
        /** ISO 3166-1 alfa-2. */
        public string $pais,
        /** Departamento o estado (en EE. UU. y Canadá, el código de 2 letras). */
        public ?string $region,
        public string $ciudad,
        public string $direccion,
        public ?string $codigoPostal,
    ) {}

    /**
     * @param array<string, mixed> $datos
     *
     * @throws VentaRechazada
     */
    public static function desdeArray(array $datos): self
    {
        $texto = static fn(string $campo, int $max) => ($v = trim((string) ($datos[$campo] ?? ""))) !== "" ? mb_substr($v, 0, $max) : null;

        $pais = strtoupper($texto("pais", 2) ?? "");
        if (!preg_match('/^[A-Z]{2}$/', $pais)) {
            throw new VentaRechazada("Indique el país de la dirección de su tarjeta.", "facturacion_pais");
        }
        $ciudad = $texto("ciudad", 50) ?? throw new VentaRechazada("Indique la ciudad de la dirección de su tarjeta.", "facturacion_ciudad");
        $direccion = $texto("direccion", 60) ?? throw new VentaRechazada("Indique la dirección de su tarjeta.", "facturacion_direccion");
        $region = $texto("region", 50);
        $codigoPostal = $texto("codigoPostal", 10);

        // EE. UU. y Canadá: el banco valida estado y código postal.
        if (in_array($pais, ["US", "CA"], true)) {
            if ($region === null || !preg_match('/^[A-Za-z]{2}$/', $region)) {
                throw new VentaRechazada("Indique el estado o provincia con su código de 2 letras (p. ej. CA, TX, ON).", "facturacion_region");
            }
            $region = strtoupper($region);
            $valido = $pais === "US" ? '/^\d{5}(-?\d{4})?$/' : '/^[A-Za-z]\d[A-Za-z] ?\d[A-Za-z]\d$/';
            if ($codigoPostal === null || !preg_match($valido, $codigoPostal)) {
                throw new VentaRechazada("Indique un código postal válido.", "facturacion_codigo_postal");
            }
        }

        return new self($pais, $region, $ciudad, $direccion, $codigoPostal);
    }
}
