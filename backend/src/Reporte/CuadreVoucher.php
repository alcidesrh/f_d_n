<?php

declare(strict_types=1);

namespace App\Reporte;

/**
 * Cuadre de voucher (puro): los boletos emitidos sin cobro, agrupados por
 * estación y día de venta, con cantidad e importe (el precio que habrían
 * costado) por grupo y en total, por moneda.
 */
final readonly class CuadreVoucher
{
    /** @var list<array{estacion: string, dia: \DateTimeImmutable, filas: list<LineaBoleto>, totales: array<string, array{cantidad: int, total: int}>}> */
    public array $grupos;
    /** @var array<string, array{cantidad: int, total: int}> */
    public array $totales;

    /** @param list<LineaBoleto> $lineas */
    public function __construct(
        public string $rotulo,
        public ?string $estacion,
        public ?string $empresa,
        array $lineas,
    ) {
        $porGrupo = [];
        foreach ($lineas as $l) {
            $porGrupo[$l->estacionVenta . "|" . $l->diaVenta()][] = $l;
        }
        ksort($porGrupo);

        $grupos = [];
        foreach ($porGrupo as $filas) {
            $grupos[] = [
                "estacion" => $filas[0]->estacionVenta,
                "dia" => new \DateTimeImmutable($filas[0]->diaVenta()),
                "filas" => $filas,
                "totales" => self::totalizar($filas),
            ];
        }
        $this->grupos = $grupos;
        $this->totales = self::totalizar($lineas);
    }

    public function cantidad(): int
    {
        return array_sum(array_column($this->totales, "cantidad"));
    }

    public function estado(LineaBoleto $l): string
    {
        return match ($l->estado) {
            "transito" => "Tránsito",
            default => ucfirst($l->estado),
        };
    }

    /**
     * @param list<LineaBoleto> $lineas
     * @return array<string, array{cantidad: int, total: int}>
     */
    private static function totalizar(array $lineas): array
    {
        $totales = [];
        foreach ($lineas as $l) {
            $totales[$l->moneda]["cantidad"] = ($totales[$l->moneda]["cantidad"] ?? 0) + 1;
            $totales[$l->moneda]["total"] = ($totales[$l->moneda]["total"] ?? 0) + $l->centavos;
        }
        ksort($totales);

        return $totales;
    }

    /** @return array<string, mixed> cifras para la vista previa de la pantalla */
    public function resumen(): array
    {
        return [
            "cantidad" => $this->cantidad(),
            "dias" => count(array_unique(array_map(static fn(array $g) => $g["dia"]->format("Y-m-d"), $this->grupos))),
            "totales" => array_map(static fn(string $m, array $t) => ["moneda" => $m, ...$t], array_keys($this->totales), array_values($this->totales)),
        ];
    }
}
