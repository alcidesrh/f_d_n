<?php

declare(strict_types=1);

namespace App\Reporte;

use App\Reporte\Encomienda\DetalleFacturaEncomiendas;
use App\Reporte\Encomienda\DetalleGeneralEncomiendas;
use App\Reporte\Encomienda\LineaEncomienda;
use App\Reporte\Xlsx\LibroXlsx as L;
use Psr\Clock\ClockInterface;

/** Excel de los detalles (una hoja con filtro y totales): factura de boletos, factura y general de encomiendas. */
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

    public function facturaEncomiendas(DetalleFacturaEncomiendas $d, string $usuario): string
    {
        $columnas = ["Encomienda", "Fecha y hora", "Tipo", "Serie DTE", "Número DTE", "Origen", "Destino", "Cliente facturado", "Usuario", "Moneda", "Importe"];
        $libro = $this->encabezado("Detalle de factura de encomiendas", [12, 20, 12, 14, 15, 22, 22, 28, 14, 9, 14], count($columnas), [
            ["Fecha de cobro", $d->rotulo, "Empresa", $d->empresa ?? "Todas"],
            ["Estación", $d->estacion ?? "Todas", "Usuario", $usuario],
        ]);
        $encabezado = $libro->fila($columnas, L::ENCABEZADO, 28);
        $libro->congelar($encabezado + 1);
        foreach ($d->filas as $l) {
            $libro->fila([
                [$l->codigo, L::CENTRO],
                [DetalleFacturaEncomiendas::cobro($l), L::FECHA_HORA],
                $l->esPorCobrar() ? "Por cobrar" : "Pagada",
                [$l->serie ?? "", L::CENTRO],
                [DetalleFacturaEncomiendas::dte($l), $l->dte === null ? L::APAGADO : L::CENTRO],
                $l->origen,
                $l->destino,
                $l->clienteFactura ?? $l->remitente,
                DetalleFacturaEncomiendas::cobrador($l),
                [$l->moneda, L::CENTRO],
                [$l->total / 100, L::DINERO],
            ]);
        }
        $libro->autofiltro($encabezado, $encabezado + max(count($d->filas), 1), count($columnas));
        $libro->saltar();
        $libro->fila([...array_fill(0, 8, ["", L::TOTAL_TEXTO]), ["Cantidad", L::TOTAL_TEXTO], [$d->cantidad, L::TOTAL_ENTERO], [$d->total / 100, L::TOTAL_DINERO]], L::TOTAL_TEXTO);

        return $libro->contenido();
    }

    public function generalEncomiendas(DetalleGeneralEncomiendas $d, string $usuario): string
    {
        $columnas = ["Encomienda", "Fecha y hora", "Empresa", "Origen", "Destino", "Documento", "Estado", "Bus", "Remitente", "Destinatario", "Bultos", "Contenido", "Por cobrar", "Pagado"];
        $libro = $this->encabezado("Detalle general de encomiendas", [12, 20, 14, 22, 22, 24, 13, 8, 26, 26, 8, 40, 13, 13], count($columnas), [
            ["Fecha", $d->rotulo, "Empresa", $d->empresa ?? "Todas"],
            ["Origen", $d->origen ?? "Todos", "Destino", $d->destino ?? "Todos"],
            ["Usuario", $usuario],
        ]);
        $encabezado = $libro->fila($columnas, L::ENCABEZADO, 28);
        $libro->congelar($encabezado + 1);
        foreach ($d->filas as $l) {
            $libro->fila([
                [$l->codigo, L::CENTRO],
                [$l->creada, L::FECHA_HORA],
                $l->empresa,
                $l->origen,
                $l->destino,
                $l->documento(),
                [$l->estadoEtiqueta(), $l->anulada() ? L::APAGADO : L::NORMAL],
                [$l->bus ?? "", L::CENTRO],
                $l->remitente,
                $l->destinatario,
                [$l->bultos, L::ENTERO],
                $l->contenido,
                [$l->porCobrar() / 100, L::DINERO],
                [$l->pagado() / 100, L::DINERO],
            ]);
        }
        $libro->autofiltro($encabezado, $encabezado + max(count($d->filas), 1), count($columnas));
        $libro->saltar();
        $libro->fila([...array_fill(0, 9, ["", L::TOTAL_TEXTO]), ["Cantidad", L::TOTAL_TEXTO], [$d->totales["cantidad"], L::TOTAL_ENTERO], ["", L::TOTAL_TEXTO], [$d->totales["porCobrar"] / 100, L::TOTAL_DINERO], [$d->totales["pagado"] / 100, L::TOTAL_DINERO]], L::TOTAL_TEXTO);

        return $libro->contenido();
    }

    /**
     * Libro con el título y la ficha de parámetros (pares etiqueta/valor por fila).
     *
     * @param list<int> $anchos
     * @param list<list<string>> $ficha
     */
    private function encabezado(string $titulo, array $anchos, int $columnas, array $ficha): L
    {
        $libro = (new L(mb_substr($titulo, 0, 31)))->anchos($anchos);
        $fila = $libro->fila([["Transporte Fuente del Norte", L::SUBTITULO]], L::SUBTITULO);
        $libro->combinar($fila, 0, $columnas - 1);
        $fila = $libro->fila([[$titulo, L::TITULO]], L::TITULO, 24);
        $libro->combinar($fila, 0, $columnas - 1);
        foreach ($ficha as $i => $pares) {
            $celdas = [];
            foreach (array_chunk($pares, 2) as [$etiqueta, $valor]) {
                array_push($celdas, [$etiqueta, L::ETIQUETA], null, [$valor, L::VALOR], null);
            }
            if ($i === 0) {
                array_push($celdas, null, ["Generado", L::ETIQUETA], [$this->reloj->now()->format("d/m/Y H:i"), L::VALOR]);
            }
            $libro->fila($celdas, L::NORMAL);
        }
        $libro->saltar();

        return $libro;
    }
}
