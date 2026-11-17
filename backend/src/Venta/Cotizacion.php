<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use Money\Money;

/**
 * Precio de cada asiento pedido y el total. Con descuento (ADR-066), `precio`
 * es lo que se cobra y la línea lleva además `lista` (la tarifa), `descuento`
 * (lo descontado) y `motivo`.
 */
final readonly class Cotizacion
{
    /**
     * @param list<array{asiento: Asiento, precio: Money, tarifaId: ?int, lista?: Money, descuento?: ?Descuento\Descuento}> $lineas
     */
    public function __construct(
        public array $lineas,
        public Money $total,
    ) {}

    public function conRecargo(EnLinea\Recargo $recargo): self
    {
        $lineas = array_map(static fn(array $l) => [...$l, "precio" => $recargo->aplicar($l["precio"])], $this->lineas);
        $total = array_reduce($lineas, static fn(?Money $t, array $l) => $t === null ? $l["precio"] : $t->add($l["precio"]), null);

        return new self($lineas, $total ?? $this->total);
    }

    /**
     * Con el descuento de cada asiento (por id; null o ausente: sin descuento).
     * Lo que ya traía uno lo reemplaza: se calcula siempre sobre la tarifa.
     *
     * @param array<int, ?Descuento\Descuento> $descuentos
     */
    public function conDescuentos(array $descuentos): self
    {
        $lineas = array_map(static function (array $l) use ($descuentos) {
            $lista = $l["lista"] ?? $l["precio"];
            $d = $descuentos[(int) $l["asiento"]->getId()] ?? null;
            if ($d === null) {
                unset($l["descuento"]);

                return [...$l, "precio" => $lista, "lista" => $lista];
            }

            return [...$l, "precio" => $lista->subtract(Descuento\ReglasDescuento::importe($lista, $d->porciento)), "lista" => $lista, "descuento" => $d];
        }, $this->lineas);
        $total = array_reduce($lineas, static fn(?Money $t, array $l) => $t === null ? $l["precio"] : $t->add($l["precio"]), null);

        return new self($lineas, $total ?? $this->total);
    }

    /** El descuento de un asiento (null si no lleva). */
    public function descuentoDe(Asiento $asiento): ?Descuento\Descuento
    {
        foreach ($this->lineas as $l) {
            if ($l["asiento"] === $asiento) {
                return $l["descuento"] ?? null;
            }
        }

        return null;
    }

    /** Lo descontado a un asiento (cero si no lleva descuento). */
    public function rebajaDe(Asiento $asiento): Money
    {
        foreach ($this->lineas as $l) {
            if ($l["asiento"] === $asiento) {
                return ($l["lista"] ?? $l["precio"])->subtract($l["precio"]);
            }
        }
        throw new \OutOfBoundsException("Asiento sin cotizar.");
    }

    public function precioDe(Asiento $asiento): Money
    {
        foreach ($this->lineas as $l) {
            if ($l["asiento"] === $asiento) {
                return $l["precio"];
            }
        }
        throw new \OutOfBoundsException("Asiento sin cotizar.");
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            "asientos" => array_map(static fn(array $l) => [
                "asiento" => $l["asiento"]->getId(),
                "numero" => $l["asiento"]->getNumero(),
                "clase" => $l["asiento"]->getClase()->value,
                "precio" => Boleto\DatosBoleto::importe($l["precio"]),
                "tarifa" => $l["tarifaId"],
                // Con descuento (ADR-066): la tarifa, lo descontado y por qué.
                "lista" => Boleto\DatosBoleto::importe($l["lista"] ?? $l["precio"]),
                "descuento" => isset($l["descuento"]) ? Boleto\DatosBoleto::importe(($l["lista"] ?? $l["precio"])->subtract($l["precio"])) : null,
                "motivo" => isset($l["descuento"]) ? $l["descuento"]->motivo : null,
            ], $this->lineas),
            "total" => Boleto\DatosBoleto::importe($this->total),
        ];
    }
}
