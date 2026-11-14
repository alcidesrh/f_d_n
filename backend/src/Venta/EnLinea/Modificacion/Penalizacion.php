<?php

declare(strict_types=1);

namespace App\Venta\EnLinea\Modificacion;

use App\Venta\Excepcion\VentaRechazada;
use Money\Currency;
use Money\Money;

/**
 * Lo que el cliente paga por modificar su compra en la página (aparte de la
 * diferencia de precio), según `ConfiguracionPagina`: un monto fijo una vez
 * por modificación o por cada asiento cambiado; 0 = sin penalización.
 */
final readonly class Penalizacion
{
    public const MAXIMO_QUETZALES = 10000;

    private function __construct(
        /** Monto con dos decimales, `"25.00"`. */
        public string $quetzales,
        public bool $porAsiento,
    ) {}

    /** `"25"`, `25.5`, `"25,50"` → `"25.50"`; entre 0 y `MAXIMO_QUETZALES`. */
    public static function de(string|int|float $quetzales, bool $porAsiento = false): self
    {
        $texto = is_string($quetzales) ? trim(str_replace(",", ".", $quetzales)) : (string) $quetzales;
        if ($texto === "") {
            $texto = "0";
        }
        if (!is_numeric($texto)) {
            throw new VentaRechazada("La penalización debe ser un número.", "penalizacion_invalida");
        }
        $valor = round((float) $texto, 2);
        if ($valor < 0 || $valor > self::MAXIMO_QUETZALES) {
            throw new VentaRechazada(sprintf("La penalización debe estar entre Q0.00 y Q%s.", number_format(self::MAXIMO_QUETZALES, 2)), "penalizacion_invalida");
        }

        return new self(number_format($valor, 2, ".", ""), $porAsiento);
    }

    public static function ninguna(): self
    {
        return new self("0.00", false);
    }

    public function unitaria(string $moneda = "GTQ"): Money
    {
        return new Money((int) round((float) $this->quetzales * 100), new Currency($moneda));
    }

    /**
     * Lo que se cobra en una modificación: nada si no cambia ningún asiento
     * comprado (agregar asientos es comprar: no se penaliza); si no, una vez
     * o por cada asiento cambiado.
     */
    public function para(int $asientosCambiados, string $moneda = "GTQ"): Money
    {
        $unitaria = $this->unitaria($moneda);
        if ($asientosCambiados <= 0 || $unitaria->isZero()) {
            return new Money(0, new Currency($moneda));
        }
        if ($this->porAsiento) {
            return $unitaria->multiply((string) $asientosCambiados);
        }

        return $unitaria;
    }
}
