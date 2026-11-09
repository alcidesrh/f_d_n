<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoAsiento;
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
        return $this->renderizar(array_map(static fn(BoletoVenta $v) => [$v, null], $ventas));
    }

    /** El PDF de un solo boleto: la página de su venta con ese asiento, como la reimpresión de la pantalla de boletos. */
    public function generarBoleto(BoletoAsiento $boleto): string
    {
        return $this->renderizar([[$boleto->getBoletoVenta(), [(int) $boleto->getId()]]]);
    }

    public static function nombreArchivo(BoletoVenta $venta): string
    {
        return sprintf("boleto_%d.pdf", $venta->getId());
    }

    /** Por id de boleto (el que muestra la pantalla de boletos), no de venta. */
    public static function nombreArchivoBoleto(BoletoAsiento $boleto): string
    {
        return sprintf("boleto_%d.pdf", $boleto->getId());
    }

    /** @param list<array{0: BoletoVenta, 1: list<int>|null}> $paginas venta y boletos que imprime (null = los vivos de la venta) */
    private function renderizar(array $paginas): string
    {
        $html = $this->twig->render("venta/boleto_pdf.html.twig", [
            "boletos" => array_map(fn(array $p) => [
                "b" => $this->comprobantes->de($p[0], $p[1]),
                "codigoBarras" => base64_encode(Code128::svg(sprintf("%08d", $p[0]->getId()))),
            ], $paginas),
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
}
