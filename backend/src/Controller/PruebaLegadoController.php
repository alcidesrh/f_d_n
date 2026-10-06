<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\PruebaLegado\ReportesLegado;
use App\PruebaLegado\VentaLegado;
use App\Reporte\FiltroCuadre;
use App\Reporte\FiltroDetalle;
use App\Reporte\ReportePdf;
use App\Reporte\ReporteRechazado;
use App\Reporte\ReporteXlsx;
use App\Venta\Excepcion\VentaRechazada;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * TEMPORAL — prueba de la venta y los reportes con datos reales del sistema anterior (solo
 * lectura; la venta es una simulación que no guarda nada). Mismos permisos y mismas formas JSON
 * que `/api/venta/*` y `/api/reportes/*`; la pantalla solo cambia el prefijo. Para quitarlo:
 * borrar este archivo y `src/PruebaLegado/` (ver también `frontend/src/temporal/legado/`).
 */
#[AsController]
#[Route("/api/prueba-legado", name: "api_prueba_legado_")]
final class PruebaLegadoController extends AbstractController
{
    private const XLSX = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

    public function __construct(
        private readonly VentaLegado $venta,
        private readonly ReportesLegado $reportes,
    ) {}

    // ─── Venta ───────────────────────────────────────────────────────

    #[Route("/venta/estaciones", name: "estaciones", methods: ["GET"])]
    public function estaciones(): Response
    {
        $this->denyAccessUnlessGranted(VentaController::VENDER);

        return $this->responder(fn() => $this->venta->estaciones());
    }

    #[Route("/venta/salidas", name: "salidas", methods: ["GET"])]
    public function salidas(Request $request): Response
    {
        $this->denyAccessUnlessGranted(VentaController::VENDER);
        $dia = \DateTimeImmutable::createFromFormat("!Y-m-d", (string) $request->query->get("fecha"));
        if ($dia === false) {
            return $this->json(["error" => "Fecha inválida (AAAA-MM-DD)."], Response::HTTP_BAD_REQUEST);
        }

        return $this->responder(fn() => $this->venta->salidas($dia, $request->query->getInt("estacion") ?: null));
    }

    #[Route("/venta/salidas/{id<\d+>}", name: "salida", methods: ["GET"])]
    public function salida(int $id): Response
    {
        $this->denyAccessUnlessGranted(VentaController::VENDER);

        return $this->responder(fn() => $this->venta->detalle($id));
    }

    #[Route("/venta/salidas/{id<\d+>}/ocupacion", name: "ocupacion", methods: ["GET"])]
    public function ocupacion(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(VentaController::VENDER);

        return $this->responder(fn() => ["asientos" => $this->venta->ocupacion($id, $request->query->getInt("trayecto") ?: null)]);
    }

    #[Route("/venta/cotizacion", name: "cotizacion", methods: ["POST"])]
    public function cotizacion(Request $request): Response
    {
        $this->denyAccessUnlessGranted(VentaController::VENDER);

        return $this->responder(function () use ($request) {
            $d = $request->toArray();

            return $this->venta->cotizar(
                (int) ($d["salida"] ?? 0),
                isset($d["trayecto"]) ? (int) $d["trayecto"] : null,
                array_map("intval", (array) ($d["asientos"] ?? [])),
                (bool) ($d["cobrarTrayectoCompleto"] ?? false),
                (bool) ($d["cortesia"] ?? false),
            );
        });
    }

    /** Simula la venta: valida, cotiza y devuelve el comprobante sin guardar nada. */
    #[Route("/venta/ventas", name: "vender", methods: ["POST"])]
    public function vender(Request $request, #[CurrentUser] Usuario $usuario): Response
    {
        $this->denyAccessUnlessGranted(VentaController::VENDER);

        return $this->responder(fn() => $this->venta->simularVenta($request->toArray(), $usuario->getUsername()), Response::HTTP_CREATED);
    }

    #[Route("/venta/ventas/{id<\d+>}/pdf", name: "venta_pdf", methods: ["GET"])]
    public function ventaPdf(): Response
    {
        return $this->json(["error" => "La venta es una simulación: no hay PDF guardado.", "codigo" => "simulada"], Response::HTTP_NOT_IMPLEMENTED);
    }

    // ─── Reportes ────────────────────────────────────────────────────

    #[Route("/reportes/opciones", name: "reportes_opciones", methods: ["GET"])]
    public function opciones(): Response
    {
        $this->denyAccessUnlessGranted(ReporteController::VER);

        return $this->responder(fn() => $this->reportes->opciones());
    }

    #[Route("/reportes/cuadre-venta-boletos", name: "reportes_cuadre", methods: ["GET"])]
    public function cuadre(Request $request, #[CurrentUser] Usuario $usuario, ReportePdf $pdf): Response
    {
        $this->denyAccessUnlessGranted(ReporteController::VER);

        return $this->responder(function () use ($request, $usuario, $pdf) {
            $filtro = FiltroCuadre::desdeQuery($request->query->all());
            $formato = $this->formato($request, ["pdf", "resumen"]);
            $cuadre = $this->reportes->cuadre($filtro);

            return $formato === "resumen"
                ? $this->json($cuadre->resumen())
                : $this->archivo($pdf->cuadre($cuadre, $usuario->getUsername()), "application/pdf", sprintf("cuadre_legado_%s.pdf", $filtro->fecha->format("Ymd")));
        });
    }

    #[Route("/reportes/detalle-factura-boletos", name: "reportes_detalle", methods: ["GET"])]
    public function detalle(Request $request, #[CurrentUser] Usuario $usuario, ReportePdf $pdf, ReporteXlsx $xlsx): Response
    {
        $this->denyAccessUnlessGranted(ReporteController::VER);

        return $this->responder(function () use ($request, $usuario, $pdf, $xlsx) {
            $filtro = FiltroDetalle::desdeQuery($request->query->all());
            $formato = $this->formato($request, ["pdf", "xlsx", "resumen"]);
            $detalle = $this->reportes->detalle($filtro);
            $nombre = sprintf("detalle_legado_%s_%s", $filtro->desde->format("Ymd"), $filtro->hasta->format("Ymd"));

            return match ($formato) {
                "resumen" => $this->json($detalle->resumen()),
                "xlsx" => $this->archivo($xlsx->detalle($detalle, $usuario->getUsername()), self::XLSX, "{$nombre}.xlsx"),
                default => $this->archivo($pdf->detalle($detalle, $usuario->getUsername()), "application/pdf", "{$nombre}.pdf"),
            };
        });
    }

    // ─── Apoyo ───────────────────────────────────────────────────────

    /** @param callable(): (array<mixed>|Response) $operacion */
    private function responder(callable $operacion, int $estado = Response::HTTP_OK): Response
    {
        try {
            $r = $operacion();

            return $r instanceof Response ? $r : $this->json($r, $estado);
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        } catch (ReporteRechazado $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        } catch (\PDOException $e) {
            return $this->json(["error" => "El sistema legado no responde: " . $e->getMessage(), "codigo" => "legado_no_disponible"], Response::HTTP_SERVICE_UNAVAILABLE);
        }
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
