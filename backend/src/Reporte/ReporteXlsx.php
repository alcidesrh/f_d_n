<?php

declare(strict_types=1);

namespace App\Reporte;

use App\Reporte\Xlsx\LibroXlsx as L;
use Psr\Clock\ClockInterface;

/** Excel del detalle de factura de boletos (una hoja con filtro y totales por moneda). */
final class ReporteXlsx
{
    private const COLUMNAS = ["Venta", "Fecha y hora", "Boleto", "Factura", "Número DTE", "Serie DTE", "Asiento", "Origen", "Destino", "Descripción", "Usuario", "Moneda", "Importe"];

    public function __construct(private readonly ClockInterface $reloj) {}

    public function detalle(DetalleFacturaBoletos $d, string $usuario): string
    {
        $libro = (new L("Detalle de factura"))->anchos([9, 20, 11, 11, 15, 14, 9, 20, 20, 30, 14, 9, 14]);
        $ultima = count(self::COLUMNAS) - 1;

        $fila = $libro->fila([["Transporte Fuente del Norte", L::SUBTITULO]], L::SUBTITULO);
        $libro->combinar($fila, 0, $ultima);
        $fila = $libro->fila([["Detalle de factura de boletos", L::TITULO]], L::TITULO, 24);
        $libro->combinar($fila, 0, $ultima);
        $libro->fila([["Fecha de venta", L::ETIQUETA], null, [$d->rotulo, L::VALOR], null, ["Empresa", L::ETIQUETA], [$d->empresa ?? "Todas", L::VALOR], null, null, null, ["Generado", L::ETIQUETA], [$this->reloj->now()->format("d/m/Y H:i"), L::VALOR]], L::NORMAL);
        $libro->fila([["Estación de venta", L::ETIQUETA], null, [$d->estacion ?? "Todas", L::VALOR], null, ["Usuario", L::ETIQUETA], [$usuario, L::VALOR]], L::NORMAL);
        $libro->saltar();

        $encabezado = $libro->fila(self::COLUMNAS, L::ENCABEZADO, 28);
        $libro->congelar($encabezado + 1);

        foreach ($d->filas as $l) {
            $libro->fila([
                [$l->ventaId, L::CENTRO],
                [$l->vendida, L::FECHA_HORA],
                [$l->boletoId, L::CENTRO],
                [$l->facturaId, L::CENTRO],
                [DetalleFacturaBoletos::estadoDte($l), $l->dte === null ? L::APAGADO : L::CENTRO],
                [$l->serie ?? "", L::CENTRO],
                [$l->asiento, L::ENTERO],
                $l->origen,
                $l->destino,
                DetalleFacturaBoletos::descripcion($l),
                $l->usuario,
                [$l->moneda, L::CENTRO],
                [$l->cobrado() / 100, L::DINERO],
            ]);
        }
        $libro->autofiltro($encabezado, $encabezado + max(count($d->filas), 1), count(self::COLUMNAS));

        $libro->saltar();
        foreach ($d->totales as $moneda => $t) {
            $libro->fila([
                ...array_fill(0, 5, ["", L::TOTAL_TEXTO]),
                ["Cantidad", L::TOTAL_TEXTO],
                [$t["cantidad"], L::TOTAL_ENTERO],
                ["", L::TOTAL_TEXTO],
                ["", L::TOTAL_TEXTO],
                ["", L::TOTAL_TEXTO],
                ["", L::TOTAL_TEXTO],
                ["Total {$moneda}", L::TOTAL_TEXTO],
                [$t["total"] / 100, L::TOTAL_DINERO],
            ], L::TOTAL_TEXTO);
        }

        return $libro->contenido();
    }
}
