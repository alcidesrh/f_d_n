<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

/** Detalle de guías internas (puro): las encomiendas sin cobro, por día de emisión. */
final readonly class GuiaInternaEncomiendas
{
    /** @var list<array{dia: \DateTimeImmutable, filas: list<LineaEncomienda>}> */
    public array $grupos;
    /** @var array{cantidad: int, bultos: int, total: int, pagado: int, porCobrar: int} */
    public array $totales;

    /** @param list<LineaEncomienda> $lineas */
    public function __construct(
        public string $rotulo,
        public ?string $estacion,
        public ?string $empresa,
        array $lineas,
    ) {
        $grupos = [];
        foreach (Totales::agrupar($lineas, static fn(LineaEncomienda $l) => $l->diaCreada()) as $dia => $filas) {
            $grupos[] = ["dia" => new \DateTimeImmutable((string) $dia), "filas" => $filas];
        }
        $this->grupos = $grupos;
        $this->totales = Totales::de($lineas);
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        return [
            "cantidad" => $this->totales["cantidad"],
            "bultos" => $this->totales["bultos"],
            "dias" => count($this->grupos),
            "anuladas" => count(array_filter(array_merge([], ...array_column($this->grupos, "filas")), static fn(LineaEncomienda $l) => $l->anulada())),
        ];
    }
}
