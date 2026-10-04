<?php

declare(strict_types=1);

namespace App\Reporte;

use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Clock\ClockInterface;
use Twig\Environment;

/**
 * PDF de los reportes de venta. Plantillas: `templates/reporte/*.html.twig`
 * (dompdf: CSS 2.1, sin flex/grid). El cuadre va en vertical; el detalle,
 * por sus muchas columnas, en horizontal.
 */
final class ReportePdf
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ClockInterface $reloj,
    ) {}

    public function cuadre(CuadreVentaBoletos $c, string $usuario): string
    {
        return $this->pdf("reporte/cuadre_venta_boletos.html.twig", ["c" => $c], $usuario, "portrait");
    }

    public function detalle(DetalleFacturaBoletos $d, string $usuario): string
    {
        return $this->pdf("reporte/detalle_factura_boletos.html.twig", ["d" => $d], $usuario, "landscape");
    }

    /** @param array<string, mixed> $datos */
    private function pdf(string $plantilla, array $datos, string $usuario, string $orientacion): string
    {
        $html = $this->twig->render($plantilla, $datos + [
            "usuario" => $usuario,
            "generado" => $this->reloj->now()->format("d/m/Y H:i"),
            "dinero" => new Dinero(),
        ]);

        $opciones = new Options();
        $opciones->setIsRemoteEnabled(false);
        $opciones->setDefaultFont("DejaVu Sans");
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, "UTF-8");
        $pdf->setPaper("letter", $orientacion);
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $fuente = $pdf->getFontMetrics()->getFont("DejaVu Sans");
        $canvas->page_text($canvas->get_width() - 140, $canvas->get_height() - 26, "Página {PAGE_NUM} de {PAGE_COUNT}", $fuente, 8, [0.42, 0.45, 0.5]);

        return (string) $pdf->output();
    }
}
