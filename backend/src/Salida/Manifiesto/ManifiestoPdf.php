<?php

declare(strict_types=1);

namespace App\Salida\Manifiesto;

use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Clock\ClockInterface;
use Twig\Environment;

/**
 * PDF de los manifiestos de una salida: el interno (por lugar de emisión,
 * con importes) y el del piloto (por asiento, con nacionalidad y firmas).
 * Plantillas: `templates/salida/manifiesto_{interno,piloto}.html.twig`.
 */
final class ManifiestoPdf
{
    public const INTERNO = "interno";
    public const PILOTO = "piloto";
    public const TIPOS = [self::INTERNO, self::PILOTO];

    public function __construct(
        private readonly Environment $twig,
        private readonly ClockInterface $reloj,
    ) {}

    public function generar(string $tipo, Manifiesto $manifiesto, string $usuario): string
    {
        $html = $this->twig->render(sprintf("salida/manifiesto_%s.html.twig", $tipo), [
            "m" => $manifiesto,
            "usuario" => $usuario,
            "generado" => $this->reloj->now()->format("d/m/Y H:i"),
            "fecha" => $manifiesto->fecha->format("d/m/Y H:i"),
            "total" => Manifiesto::importe(Manifiesto::cobrado($manifiesto->pasajeros), $manifiesto->moneda()),
            "grupos" => array_map(
                static fn(array $g) => ["pasajeros" => $g, "importe" => Manifiesto::importe(Manifiesto::cobrado($g), $g[0]->moneda)],
                $manifiesto->porEmision(),
            ),
        ]);

        $opciones = new Options();
        $opciones->setIsRemoteEnabled(false);
        $opciones->setDefaultFont("DejaVu Sans");
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, "UTF-8");
        $pdf->setPaper("letter", "landscape");
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $fuente = $pdf->getFontMetrics()->getFont("DejaVu Sans");
        $canvas->page_text($canvas->get_width() - 140, $canvas->get_height() - 26, "Página {PAGE_NUM} de {PAGE_COUNT}", $fuente, 8, [0.42, 0.45, 0.5]);

        return (string) $pdf->output();
    }

    public static function nombreArchivo(string $tipo, Manifiesto $m): string
    {
        return sprintf("manifiesto_%s_%d.pdf", $tipo, $m->salidaId);
    }

    public static function titulo(string $tipo): string
    {
        return $tipo === self::PILOTO ? "Manifiesto del piloto" : "Manifiesto interno de pasajeros";
    }
}
