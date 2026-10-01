<?php

declare(strict_types=1);

namespace App\Venta\EnLinea;

use App\Venta\Excepcion\VentaRechazada;
use Money\Money;

/**
 * Porciento que la página web suma a la tarifa (ADR-023). Se redondea al
 * centavo, por asiento: el boleto registra el precio con recargo y la
 * factura lo cobra tal cual.
 */
final readonly class Recargo
{
    public const MAXIMO = 100;

    private function __construct(public string $porciento) {}

    /** `"7.5"`, `7.5` → `"7.50"`; entre 0 y `MAXIMO`, con dos decimales. */
    public static function de(string|int|float $porciento): self
    {
        $texto = is_string($porciento) ? trim(str_replace(",", ".", $porciento)) : (string) $porciento;
        if (!is_numeric($texto)) {
            throw new VentaRechazada("El recargo debe ser un número.", "recargo_invalido");
        }
        $valor = round((float) $texto, 2);
        if ($valor < 0 || $valor > self::MAXIMO) {
            throw new VentaRechazada(sprintf("El recargo debe estar entre 0 y %d %%.", self::MAXIMO), "recargo_invalido");
        }

        return new self(number_format($valor, 2, ".", ""));
    }

    public static function ninguno(): self
    {
        return new self("0.00");
    }

    public function aplicar(Money $precio): Money
    {
        if ($this->porciento === "0.00" || $precio->isZero()) {
            return $precio;
        }

        return $precio->add($precio->multiply(bcdiv($this->porciento, "100", 6), Money::ROUND_HALF_UP));
    }
}
