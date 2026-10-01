<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoVenta;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * PDF del boleto de una o más ventas (el que se descarga en la web y viaja
 * por correo; en ida y vuelta, una página por viaje). Plantilla:
 * `templates/venta/boleto_pdf.html.twig`.
 */
final class BoletoPdf
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Comprobantes $comprobantes,
    ) {}

    /** Un PDF con una página por venta (ida y regreso de una compra web). */
    public function generar(BoletoVenta ...$ventas): string
    {
        $html = $this->twig->render("venta/boleto_pdf.html.twig", [
            "boletos" => array_map(fn(BoletoVenta $v) => [
                "b" => $this->comprobantes->de($v),
                "codigoBarras" => base64_encode(Code128::svg(sprintf("%08d", $v->getId()))),
            ], $ventas),
        ]);

        $opciones = new Options();
        $opciones->setIsRemoteEnabled(false);
        $opciones->setDefaultFont("DejaVu Sans");
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, "UTF-8");
        $pdf->setPaper("letter");
        $pdf->render();

        return (string) $pdf->output();
    }

    public static function nombreArchivo(BoletoVenta $venta): string
    {
        return sprintf("boleto_%d.pdf", $venta->getId());
    }
}
