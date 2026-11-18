<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use Money\Money;

/** Precio de cada asiento pedido y el total. */
final readonly class Cotizacion
{
    /**
     * @param list<array{asiento: Asiento, precio: Money, tarifaId: ?int}> $lineas
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
            ], $this->lineas),
            "total" => Boleto\DatosBoleto::importe($this->total),
        ];
    }
}
