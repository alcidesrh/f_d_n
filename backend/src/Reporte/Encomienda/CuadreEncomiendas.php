<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

/**
 * Cuadre de encomiendas de un día (puro), como el del legado: por usuario
 * (lo facturado al recibir, lo registrado por cobrar, lo cobrado al entregar
 * y las guías internas), lo enviado por salida, lo que quedó sin enviar, las
 * por cobrar entregadas y las anuladas. Sin efectivo/valores (ADR-035).
 */
final readonly class CuadreEncomiendas
{
    /** @var list<array{usuario: string, nombre: string, facturado: int, facturadas: int, porCobrar: int, porCobrarCantidad: int, cobrado: int, cobradas: int, internas: int}> */
    public array $usuarios;
    /** @var array{facturado: int, facturadas: int, porCobrar: int, porCobrarCantidad: int, cobrado: int, cobradas: int, internas: int} */
    public array $totalUsuarios;
    /** @var list<array{salidaId: int, fecha: ?\DateTimeImmutable, bus: ?string, pilotos: list<string>, ruta: ?string, totales: array<string, int>}> */
    public array $salidas;
    /** @var array{cantidad: int, bultos: int, total: int, pagado: int, porCobrar: int} */
    public array $totalSalidas;
    /** @var list<LineaEncomienda> */
    public array $pendientesEnvio;
    /** @var list<LineaEncomienda> */
    public array $cobradas;
    /** @var list<LineaEncomienda> */
    public array $anuladas;

    /**
     * @param list<LineaEncomienda> $recibidas registradas ese día en la estación
     * @param list<LineaEncomienda> $cobradas  por cobrar entregadas ese día en la estación
     * @param list<LineaEncomienda> $enviadas  salieron de la estación en salidas de ese día
     * @param list<LineaEncomienda> $anuladas  registradas en la estación y anuladas ese día
     * @param array<string, string> $nombres   usuario → nombre completo
     */
    public function __construct(
        public \DateTimeImmutable $fecha,
        public ?string $estacion,
        public ?string $empresa,
        array $recibidas,
        array $cobradas,
        array $enviadas,
        array $anuladas,
        array $nombres,
    ) {
        $vacio = ["facturado" => 0, "facturadas" => 0, "porCobrar" => 0, "porCobrarCantidad" => 0, "cobrado" => 0, "cobradas" => 0, "internas" => 0];
        $porUsuario = [];
        foreach ($recibidas as $l) {
            if ($l->anulada()) {
                continue;
            }
            $u = $porUsuario[$l->usuario] ?? $vacio;
            if ($l->esPagada()) {
                $u["facturado"] += $l->total;
                ++$u["facturadas"];
            } elseif ($l->esPorCobrar()) {
                $u["porCobrar"] += $l->total;
                ++$u["porCobrarCantidad"];
            } elseif ($l->modalidad === "interna") {
                ++$u["internas"];
            }
            $porUsuario[$l->usuario] = $u;
        }
        foreach ($cobradas as $l) {
            $quien = $l->entregadaPor ?? "—";
            $porUsuario[$quien] ??= $vacio;
            $porUsuario[$quien]["cobrado"] += $l->total;
            ++$porUsuario[$quien]["cobradas"];
        }
        ksort($porUsuario);
        $usuarios = [];
        $total = $vacio;
        foreach ($porUsuario as $usuario => $cifras) {
            $usuarios[] = ["usuario" => (string) $usuario, "nombre" => $nombres[(string) $usuario] ?? "", ...$cifras];
            foreach ($cifras as $k => $v) {
                $total[$k] += $v;
            }
        }
        $this->usuarios = $usuarios;
        $this->totalUsuarios = $total;

        $salidas = [];
        foreach (Totales::agrupar($enviadas, static fn(LineaEncomienda $l) => (string) $l->salidaId) as $filas) {
            $l = $filas[0];
            $salidas[] = ["salidaId" => (int) $l->salidaId, "fecha" => $l->salidaFecha, "bus" => $l->bus, "pilotos" => $l->pilotos, "ruta" => $l->rutaSalida, "totales" => Totales::de($filas)];
        }
        usort($salidas, static fn(array $a, array $b) => [$a["fecha"], $a["salidaId"]] <=> [$b["fecha"], $b["salidaId"]]);
        $this->salidas = $salidas;
        $this->totalSalidas = Totales::de($enviadas);

        $this->pendientesEnvio = array_values(array_filter($recibidas, static fn(LineaEncomienda $l) => $l->estado === "recibida"));
        $this->cobradas = $cobradas;
        $this->anuladas = $anuladas;
    }

    /** Lo que entró a caja: lo facturado al recibir más lo cobrado al entregar. */
    public function ingreso(): int
    {
        return $this->totalUsuarios["facturado"] + $this->totalUsuarios["cobrado"];
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        return [
            ...$this->totalUsuarios,
            "ingreso" => $this->ingreso(),
            "usuarios" => count($this->usuarios),
            "salidas" => count($this->salidas),
            "pendientesEnvio" => count($this->pendientesEnvio),
            "anuladas" => count($this->anuladas),
        ];
    }
}
