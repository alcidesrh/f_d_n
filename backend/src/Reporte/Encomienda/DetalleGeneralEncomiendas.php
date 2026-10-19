<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

/**
 * Detalle general de encomiendas (puro): las registradas por día, origen y
 * destino, con lo pagado y lo por cobrar de cada grupo. Las anuladas se
 * listan pero no suman.
 */
final readonly class DetalleGeneralEncomiendas
{
    /** @var list<array{dia: \DateTimeImmutable, origen: string, destino: string, filas: list<LineaEncomienda>, totales: array<string, int>}> */
    public array $grupos;
    /** @var array{cantidad: int, bultos: int, total: int, pagado: int, porCobrar: int} */
    public array $totales;
    /** @var list<LineaEncomienda> */
    public array $filas;

    /** @param list<LineaEncomienda> $lineas */
    public function __construct(
        public string $rotulo,
        public ?string $empresa,
        public ?string $origen,
        public ?string $destino,
        array $lineas,
    ) {
        $grupos = [];
        foreach (Totales::agrupar($lineas, static fn(LineaEncomienda $l) => "{$l->diaCreada()}|{$l->origen}|{$l->destino}") as $filas) {
            $grupos[] = ["dia" => new \DateTimeImmutable($filas[0]->diaCreada()), "origen" => $filas[0]->origen, "destino" => $filas[0]->destino, "filas" => $filas, "totales" => Totales::de($filas)];
        }
        usort($grupos, static fn(array $a, array $b) => [$a["dia"], $a["origen"], $a["destino"]] <=> [$b["dia"], $b["origen"], $b["destino"]]);
        $this->grupos = $grupos;
        $this->totales = Totales::de($lineas);
        $this->filas = $lineas;
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        return [
            ...$this->totales,
            "anuladas" => count(array_filter($this->filas, static fn(LineaEncomienda $l) => $l->anulada())),
            "grupos" => count($this->grupos),
        ];
    }
}
