<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoVenta;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * PDF del boleto de una venta (el que se descarga en la web y viaja por
 * correo). Plantilla: `templates/venta/boleto_pdf.html.twig`.
 */
final class BoletoPdf
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function generar(BoletoVenta $venta): string
    {
        $html = $this->twig->render("venta/boleto_pdf.html.twig", [
            "b" => DatosBoleto::de($venta),
            "codigoBarras" => base64_encode(Code128::svg(sprintf("%08d", $venta->getId()))),
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
