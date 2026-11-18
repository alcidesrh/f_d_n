<?php

declare(strict_types=1);

namespace App\Reporte;

/** Detalle de factura de boletos (puro): un renglón por boleto vivo, con totales por moneda. */
final readonly class DetalleFacturaBoletos
{
    /** @var list<LineaBoleto> */
    public array $filas;
    /** @var array<string, array{cantidad: int, total: int}> */
    public array $totales;

    /** @param list<LineaBoleto> $lineas */
    public function __construct(
        public string $rotulo,
        public ?string $estacion,
        public ?string $empresa,
        array $lineas,
    ) {
        usort($lineas, static fn(LineaBoleto $a, LineaBoleto $b) => [$a->vendida, $a->boletoId] <=> [$b->vendida, $b->boletoId]);
        $this->filas = $lineas;

        $totales = [];
        foreach ($lineas as $l) {
            $totales[$l->moneda]["cantidad"] = ($totales[$l->moneda]["cantidad"] ?? 0) + 1;
            $totales[$l->moneda]["total"] = ($totales[$l->moneda]["total"] ?? 0) + $l->cobrado();
        }
        ksort($totales);
        $this->totales = $totales;
    }

    public function cantidad(): int
    {
        return count($this->filas);
    }

    /** Texto de la columna "Factura electrónica": el DTE o el motivo por el que no lo hay. */
    public static function estadoDte(LineaBoleto $l): string
    {
        return match (true) {
            $l->dte !== null => (string) $l->dte,
            $l->estadoFacturacion === "pendiente" => "Pendiente",
            default => "Sin factura",
        };
    }

    /** Autorización de tarjeta y/o referencia externa, para la columna "Descripción". */
    public static function descripcion(LineaBoleto $l): string
    {
        return implode(" · ", array_filter([
            $l->autorizacion !== null ? sprintf("Aut. %s", $l->autorizacion) : null,
            $l->referenciaExterna !== null ? sprintf("Ref. %s", $l->referenciaExterna) : null,
        ]));
    }

    /** @return array<string, mixed> cifras para la vista previa de la pantalla */
    public function resumen(): array
    {
        return [
            "cantidad" => $this->cantidad(),
            "totales" => array_map(static fn(string $m, array $t) => ["moneda" => $m, ...$t], array_keys($this->totales), array_values($this->totales)),
            "conTarjeta" => count(array_filter($this->filas, static fn(LineaBoleto $l) => $l->autorizacion !== null)),
            "sinFactura" => count(array_filter($this->filas, static fn(LineaBoleto $l) => $l->dte === null)),
        ];
    }
}
