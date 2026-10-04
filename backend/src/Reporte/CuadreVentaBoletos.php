<?php

declare(strict_types=1);

namespace App\Reporte;

/**
 * Cuadre de venta de boletos de un día (puro): lo vendido ese día por usuario,
 * por salida, los prepagados, las ventas con tarjeta y los boletos anulados.
 * Los boletos reasignados no entran; "Recibido" incluye lo anulado y
 * "Facturado" es lo recibido menos lo anulado.
 */
final readonly class CuadreVentaBoletos
{
    /** @var list<array{usuario: string, nombre: string, ventas: int, boletos: int, recibido: int, anulado: int, facturado: int}> */
    public array $usuarios;
    /** @var array{ventas: int, boletos: int, recibido: int, anulado: int, facturado: int} */
    public array $totalUsuarios;
    /** Salidas de la fecha (o anteriores) vendidas ese día. @var list<array{salidaId: int, bus: ?string, piloto: ?string, hora: string, descripcion: string, venta: int}> */
    public array $salidas;
    public int $totalSalidas;
    /** Salidas de días posteriores vendidas ese día. @var list<array{salidaId: int, salida: \DateTimeImmutable, descripcion: string, venta: int}> */
    public array $prepagados;
    public int $totalPrepagados;
    /** Salidas de la fecha vendidas antes en otra estación y que suben en esta. @var list<array{salidaId: int, salida: \DateTimeImmutable, descripcion: string, venta: int}> */
    public array $prepagadosOtras;
    public int $totalPrepagadosOtras;
    /** @var list<array{ventaId: int, factura: string, hora: \DateTimeImmutable, tarjeta: string, usuario: string, venta: int}> */
    public array $tarjetas;
    public int $totalTarjetas;
    /** @var list<array{ventaId: int, boletoId: int, factura: string, hora: \DateTimeImmutable, usuario: string, asiento: int, descripcion: string, importe: int}> */
    public array $anulados;
    public int $totalAnulados;

    /**
     * @param list<LineaBoleto> $ventas         boletos vendidos el día del reporte (incluye anulados)
     * @param list<LineaBoleto> $otrasEstaciones boletos de salidas de ese día vendidos antes en otra estación
     */
    public function __construct(
        public \DateTimeImmutable $fecha,
        public ?string $estacion,
        public ?string $empresa,
        public string $moneda,
        array $ventas,
        array $otrasEstaciones = [],
    ) {
        $dia = $fecha->format("Y-m-d");
        $vivos = array_values(array_filter($ventas, static fn(LineaBoleto $l) => !$l->anulado));

        $this->usuarios = self::porUsuario($ventas);
        $this->totalUsuarios = [
            "ventas" => count(array_unique(array_map(static fn(LineaBoleto $l) => $l->ventaId, $ventas))),
            "boletos" => count($ventas),
            "recibido" => array_sum(array_column($this->usuarios, "recibido")),
            "anulado" => array_sum(array_column($this->usuarios, "anulado")),
            "facturado" => array_sum(array_column($this->usuarios, "facturado")),
        ];

        $this->salidas = self::porSalida(
            array_filter($vivos, static fn(LineaBoleto $l) => $l->diaSalida() <= $dia),
            static fn(array $g, LineaBoleto $l, int $venta) => [
                "salidaId" => $l->salidaId,
                "bus" => $l->bus,
                "piloto" => $l->piloto,
                "hora" => $l->salida->format("H:i"),
                "descripcion" => $l->ruta,
                "venta" => $venta,
            ],
        );
        $this->totalSalidas = array_sum(array_column($this->salidas, "venta"));

        $conFecha = static fn(array $g, LineaBoleto $l, int $venta) => [
            "salidaId" => $l->salidaId,
            "salida" => $l->salida,
            "descripcion" => $l->ruta,
            "venta" => $venta,
        ];
        $this->prepagados = self::porSalida(array_filter($vivos, static fn(LineaBoleto $l) => $l->diaSalida() > $dia), $conFecha);
        $this->totalPrepagados = array_sum(array_column($this->prepagados, "venta"));
        $this->prepagadosOtras = self::porSalida(array_filter($otrasEstaciones, static fn(LineaBoleto $l) => !$l->anulado), $conFecha);
        $this->totalPrepagadosOtras = array_sum(array_column($this->prepagadosOtras, "venta"));

        $this->tarjetas = self::tarjetas($vivos);
        $this->totalTarjetas = array_sum(array_column($this->tarjetas, "venta"));

        $anulados = array_values(array_filter($ventas, static fn(LineaBoleto $l) => $l->anulado));
        usort($anulados, static fn(LineaBoleto $a, LineaBoleto $b) => [$a->vendida, $a->boletoId] <=> [$b->vendida, $b->boletoId]);
        $this->anulados = array_map(static fn(LineaBoleto $l) => [
            "ventaId" => $l->ventaId,
            "boletoId" => $l->boletoId,
            "factura" => $l->factura() ?? "—",
            "hora" => $l->vendida,
            "usuario" => $l->usuario,
            "asiento" => $l->asiento,
            "descripcion" => sprintf("%s - %s", $l->origen, $l->destino),
            "importe" => $l->cobrado(),
        ], $anulados);
        $this->totalAnulados = array_sum(array_column($this->anulados, "importe"));
    }

    /** @return array<string, mixed> cifras para la vista previa de la pantalla */
    public function resumen(): array
    {
        return [
            "moneda" => $this->moneda,
            ...$this->totalUsuarios,
            "usuarios" => count($this->usuarios),
            "salidas" => count($this->salidas),
            "prepagados" => count($this->prepagados) + count($this->prepagadosOtras),
            "tarjetas" => count($this->tarjetas),
            "anulados" => count($this->anulados),
        ];
    }

    /**
     * @param list<LineaBoleto> $ventas
     * @return list<array{usuario: string, nombre: string, ventas: int, boletos: int, recibido: int, anulado: int, facturado: int}>
     */
    private static function porUsuario(array $ventas): array
    {
        $grupos = [];
        foreach ($ventas as $l) {
            $grupos[$l->usuario][] = $l;
        }
        ksort($grupos);

        $filas = [];
        foreach ($grupos as $usuario => $lineas) {
            $recibido = array_sum(array_map(static fn(LineaBoleto $l) => $l->cobrado(), $lineas));
            $anulado = array_sum(array_map(static fn(LineaBoleto $l) => $l->anulado ? $l->cobrado() : 0, $lineas));
            $filas[] = [
                "usuario" => (string) $usuario,
                "nombre" => $lineas[0]->usuarioNombre,
                "ventas" => count(array_unique(array_map(static fn(LineaBoleto $l) => $l->ventaId, $lineas))),
                "boletos" => count($lineas),
                "recibido" => $recibido,
                "anulado" => $anulado,
                "facturado" => $recibido - $anulado,
            ];
        }

        return $filas;
    }

    /**
     * @param iterable<LineaBoleto> $lineas
     * @param callable(array<LineaBoleto>, LineaBoleto, int): array<string, mixed> $fila
     * @return list<array<string, mixed>>
     */
    private static function porSalida(iterable $lineas, callable $fila): array
    {
        $grupos = [];
        foreach ($lineas as $l) {
            $grupos[$l->salidaId][] = $l;
        }
        uasort($grupos, static fn(array $a, array $b) => [$a[0]->salida, $a[0]->salidaId] <=> [$b[0]->salida, $b[0]->salidaId]);

        return array_values(array_map(
            static fn(array $g) => $fila($g, $g[0], array_sum(array_map(static fn(LineaBoleto $l) => $l->cobrado(), $g))),
            $grupos,
        ));
    }

    /**
     * @param list<LineaBoleto> $vivos
     * @return list<array{ventaId: int, factura: string, hora: \DateTimeImmutable, tarjeta: string, usuario: string, venta: int}>
     */
    private static function tarjetas(array $vivos): array
    {
        $grupos = [];
        foreach ($vivos as $l) {
            if ($l->tarjeta) {
                $grupos[$l->ventaId][] = $l;
            }
        }
        uasort($grupos, static fn(array $a, array $b) => [$a[0]->vendida, $a[0]->ventaId] <=> [$b[0]->vendida, $b[0]->ventaId]);

        return array_values(array_map(static fn(array $g) => [
            "ventaId" => $g[0]->ventaId,
            "factura" => $g[0]->factura() ?? "—",
            "hora" => $g[0]->vendida,
            "tarjeta" => $g[0]->autorizacion ?? "—",
            "usuario" => sprintf("%s - %s", $g[0]->usuario, $g[0]->usuarioNombre),
            "venta" => array_sum(array_map(static fn(LineaBoleto $l) => $l->cobrado(), $g)),
        ], $grupos));
    }
}
