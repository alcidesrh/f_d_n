<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

/**
 * Detalle de factura de encomiendas (puro): una fila por encomienda cobrada
 * con factura propia, por día de cobro y destino, con subtotales. La pagada
 * se cobra al recibirla; la por cobrar, al entregarla.
 */
final readonly class DetalleFacturaEncomiendas
{
    /** @var list<array{dia: \DateTimeImmutable, destinos: list<array{destino: string, filas: list<LineaEncomienda>, total: int}>, cantidad: int, total: int}> */
    public array $grupos;
    public int $cantidad;
    public int $total;
    /** @var list<LineaEncomienda> en orden de cobro (para el Excel) */
    public array $filas;

    /** @param list<LineaEncomienda> $lineas */
    public function __construct(
        public string $rotulo,
        public ?string $estacion,
        public ?string $empresa,
        array $lineas,
    ) {
        usort($lineas, static fn(LineaEncomienda $a, LineaEncomienda $b) => [self::cobro($a), $a->id] <=> [self::cobro($b), $b->id]);
        $grupos = [];
        foreach (Totales::agrupar($lineas, static fn(LineaEncomienda $l) => self::cobro($l)->format("Y-m-d")) as $dia => $delDia) {
            $destinos = [];
            foreach (Totales::agrupar($delDia, static fn(LineaEncomienda $l) => $l->destino) as $destino => $filas) {
                $destinos[] = ["destino" => (string) $destino, "filas" => $filas, "total" => array_sum(array_map(static fn(LineaEncomienda $l) => $l->total, $filas))];
            }
            usort($destinos, static fn(array $a, array $b) => strcmp($a["destino"], $b["destino"]));
            $grupos[] = ["dia" => new \DateTimeImmutable((string) $dia), "destinos" => $destinos, "cantidad" => count($delDia), "total" => array_sum(array_column($destinos, "total"))];
        }
        $this->grupos = $grupos;
        $this->cantidad = count($lineas);
        $this->total = array_sum(array_column($grupos, "total"));
        $this->filas = $lineas;
    }

    /** Cuándo se cobró: al recibirla (pagada) o al entregarla (por cobrar). */
    public static function cobro(LineaEncomienda $l): \DateTimeImmutable
    {
        return $l->esPorCobrar() && $l->entregadaEn !== null ? $l->entregadaEn : $l->creada;
    }

    /** Quién la cobró. */
    public static function cobrador(LineaEncomienda $l): string
    {
        return $l->esPorCobrar() ? ($l->entregadaPor ?? "—") : $l->usuario;
    }

    /** Número de DTE o por qué no lo hay. */
    public static function dte(LineaEncomienda $l): string
    {
        return $l->dte !== null ? (string) $l->dte : ($l->estadoFacturacion === "pendiente" ? "Pendiente" : "Sin factura");
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        return [
            "cantidad" => $this->cantidad,
            "total" => $this->total,
            "pagadas" => count(array_filter($this->filas, static fn(LineaEncomienda $l) => $l->esPagada())),
            "porCobrar" => count(array_filter($this->filas, static fn(LineaEncomienda $l) => $l->esPorCobrar())),
            "pendientes" => count(array_filter($this->filas, static fn(LineaEncomienda $l) => $l->dte === null)),
        ];
    }
}
