<?php

declare(strict_types=1);

namespace App\Combustible;

/**
 * Costo de combustible de una salida (ADR-073). Puro: los km de su ruta, lo
 * que rinde el bus y el precio del día en quetzales por galón.
 *
 * Solo combustible. El precio que se usa es el de su día; si no hay, el último
 * anterior, y `precioEstimado` lo dice. Sin km de ruta o sin precio no hay
 * costo: `motivo` dice qué falta.
 */
final class CostoCombustible
{
    public const MONEDA = "GTQ";

    public const SIN_BUS = "sin_bus";
    public const SIN_RUTA_KM = "ruta_sin_km";
    public const SIN_PRECIO = "sin_precio";

    /**
     * @param ?string $precioFecha día del precio (`AAAA-MM-DD`), o nulo si no hay precio
     * @param ?string $dia         día de la salida (`AAAA-MM-DD`)
     *
     * @return array{kilometros: ?int, rendimientoKmGal: ?float, galones: ?float, precioGalon: ?float, precioFecha: ?string, precioEstimado: bool, moneda: string, costo: ?float, motivo: ?string}
     */
    public static function calcular(?int $kilometros, ?float $rendimientoKmGal, ?float $precioGalon, ?string $precioFecha, ?string $dia): array
    {
        $resultado = [
            "kilometros" => $kilometros,
            "rendimientoKmGal" => $rendimientoKmGal,
            "galones" => null,
            "precioGalon" => $precioGalon,
            "precioFecha" => $precioFecha,
            "precioEstimado" => $precioFecha !== null && $precioFecha !== $dia,
            "moneda" => self::MONEDA,
            "costo" => null,
            "motivo" => null,
        ];

        if ($rendimientoKmGal === null || $rendimientoKmGal <= 0) {
            return [...$resultado, "motivo" => self::SIN_BUS];
        }
        if ($kilometros === null) {
            return [...$resultado, "motivo" => self::SIN_RUTA_KM];
        }
        if ($precioGalon === null) {
            return [...$resultado, "motivo" => self::SIN_PRECIO];
        }

        $galones = $kilometros / $rendimientoKmGal;

        return [
            ...$resultado,
            "galones" => round($galones, 2),
            "costo" => round($galones * $precioGalon, 2),
        ];
    }
}
