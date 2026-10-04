<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Reporte\ConsultaReportes;
use App\Reporte\FiltroCuadre;
use App\Reporte\FiltroDetalle;
use App\Reporte\ReportePdf;
use App\Reporte\ReporteRechazado;
use App\Reporte\ReporteXlsx;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Reportes de venta de boletos (permiso `reporte.ventas`). Un usuario con
 * estación (o empresa) asignada solo ve la suya, diga lo que diga la query.
 * `formato`: `pdf` (por defecto), `xlsx` (solo el detalle) o `resumen`
 * (cifras en JSON para la vista previa de la pantalla). Los errores son
 * `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/reportes", name: "api_reportes_")]
final class ReporteController extends AbstractController
{
    public const VER = "reporte.ventas";

    private const XLSX = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

    public function __construct(
        private readonly ConsultaReportes $consulta,
        private readonly ClockInterface $reloj,
    ) {}

    /** Catálogos de los formularios y el alcance del usuario (estación/empresa fijas si las tiene). */
    #[Route("/opciones", name: "opciones", methods: ["GET"])]
    public function opciones(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::VER);
        $estacion = $usuario->getEstacion()?->getId();
        $empresa = $usuario->getEmpresa()?->getId();

        return $this->json([
            "hoy" => $this->reloj->now()->format("Y-m-d"),
            "estaciones" => $this->consulta->estaciones($estacion),
            "empresas" => $this->consulta->empresas($empresa),
            "monedas" => $this->consulta->monedas(),
            "alcance" => ["estacion" => $estacion, "empresa" => $empresa],
        ]);
    }

    #[Route("/cuadre-venta-boletos", name: "cuadre", methods: ["GET"])]
    public function cuadre(Request $request, #[CurrentUser] Usuario $usuario, ReportePdf $pdf): Response
    {
        $this->denyAccessUnlessGranted(self::VER);

        try {
            $filtro = FiltroCuadre::desdeQuery($request->query->all())->conAlcance($usuario->getEstacion()?->getId(), $usuario->getEmpresa()?->getId());
            $formato = $this->formato($request, ["pdf", "resumen"]);
            $cuadre = $this->consulta->cuadre($filtro);
        } catch (ReporteRechazado $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        if ($formato === "resumen") {
            return $this->json($cuadre->resumen());
        }

        return $this->archivo(
            $pdf->cuadre($cuadre, $usuario->getUsername()),
            "application/pdf",
            sprintf("cuadre_venta_boletos_%s.pdf", $filtro->fecha->format("Ymd")),
        );
    }

    #[Route("/detalle-factura-boletos", name: "detalle", methods: ["GET"])]
    public function detalle(Request $request, #[CurrentUser] Usuario $usuario, ReportePdf $pdf, ReporteXlsx $xlsx): Response
    {
        $this->denyAccessUnlessGranted(self::VER);

        try {
            $filtro = FiltroDetalle::desdeQuery($request->query->all())->conAlcance($usuario->getEstacion()?->getId(), $usuario->getEmpresa()?->getId());
            $formato = $this->formato($request, ["pdf", "xlsx", "resumen"]);
            $detalle = $this->consulta->detalle($filtro);
        } catch (ReporteRechazado $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        $nombre = sprintf("detalle_factura_boletos_%s_%s", $filtro->desde->format("Ymd"), $filtro->hasta->format("Ymd"));

        return match ($formato) {
            "resumen" => $this->json($detalle->resumen()),
            "xlsx" => $this->archivo($xlsx->detalle($detalle, $usuario->getUsername()), self::XLSX, "{$nombre}.xlsx"),
            default => $this->archivo($pdf->detalle($detalle, $usuario->getUsername()), "application/pdf", "{$nombre}.pdf"),
        };
    }

    /** @param list<string> $permitidos */
    private function formato(Request $request, array $permitidos): string
    {
        $formato = (string) $request->query->get("formato", "pdf");
        if (!in_array($formato, $permitidos, true)) {
            throw new ReporteRechazado(sprintf("Formato no disponible; usa %s.", implode(", ", $permitidos)), "formato_invalido", 400);
        }

        return $formato;
    }

    private function archivo(string $contenido, string $tipo, string $nombre): Response
    {
        return new Response($contenido, 200, [
            "Content-Type" => $tipo,
            "Content-Disposition" => sprintf('%s; filename="%s"', $tipo === "application/pdf" ? "inline" : "attachment", $nombre),
        ]);
    }
}
