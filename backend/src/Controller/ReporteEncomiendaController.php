<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Alcance;
use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Ubicacion;
use App\Entity\Usuario;
use App\Reporte\ConsultaReportes;
use App\Reporte\Encomienda\ConsultaReportesEncomienda;
use App\Reporte\Encomienda\FiltroEncomiendas;
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
 * Reportes de encomiendas (ADR-035). Los de operación (manifiesto de carga,
 * pendientes de entregar, detalle general) con `encomienda.ver` o
 * `reporte.encomiendas`; los de caja (cuadre, detalle de factura, guías
 * internas) solo con `reporte.encomiendas`. Con el alcance acotado
 * (ADR-039), cada reporte es de una de sus empresas y estaciones: si tiene
 * una sola queda fija; si tiene varias, elige. `formato`: `pdf` (por defecto), `xlsx` (los
 * detalles) o `resumen` (cifras para la vista previa). Errores: `{ error, codigo }`.
 */
#[AsController]
#[Route("/api/reportes/encomiendas", name: "api_reportes_encomiendas_")]
final class ReporteEncomiendaController extends AbstractController
{
    public const VER = "reporte.encomiendas";

    private const XLSX = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

    public function __construct(
        private readonly ConsultaReportesEncomienda $consulta,
        private readonly ReportePdf $pdf,
        private readonly ReporteXlsx $xlsx,
        private readonly ClockInterface $reloj,
        private readonly Autorizacion $autorizacion,
    ) {}

    /** Catálogos de los formularios, el alcance del usuario y qué reportes puede ver. */
    #[Route("/opciones", name: "opciones", methods: ["GET"])]
    public function opciones(ConsultaReportes $catalogos): JsonResponse
    {
        $this->operacion();
        $alcance = $this->alcance(false);

        return $this->json([
            "hoy" => $this->reloj->now()->format("Y-m-d"),
            "estaciones" => $catalogos->estaciones($alcance->estaciones()),
            // Origen y destino pueden ser cualquier estación aunque el usuario tenga una.
            "enclaves" => $catalogos->estaciones(),
            "empresas" => $catalogos->empresas($alcance->empresas()),
            "alcance" => ["estacion" => $alcance->estacionUnica(), "empresa" => $alcance->empresaUnica()],
            "caja" => $this->isGranted(self::VER),
        ]);
    }

    /** `?salida=&destino=`: lo que lleva la salida, por destino. */
    #[Route("/manifiesto", name: "manifiesto", methods: ["GET"])]
    public function manifiesto(Request $request, #[CurrentUser] Usuario $usuario): Response
    {
        $this->operacion();

        return $this->generar($request, $usuario, false, ["pdf", "resumen"], function (FiltroEncomiendas $f, string $formato, string $quien) {
            $m = $this->consulta->manifiesto(new FiltroEncomiendas(empresaId: $f->empresaId, destinoId: $f->destinoId, salidaId: $f->salidaId));

            return $formato === "resumen" ? $m->resumen() : [$this->pdf->encomienda("manifiesto", ["m" => $m], $quien, "landscape"), sprintf("manifiesto_encomiendas_%d", $m->salidaId)];
        }, conEstacion: false);
    }

    /** `?desde=&hasta=&estacion=&empresa=`: guías internas emitidas. */
    #[Route("/guia-interna", name: "guia_interna", methods: ["GET"])]
    public function guiaInterna(Request $request, #[CurrentUser] Usuario $usuario): Response
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->generar($request, $usuario, true, ["pdf", "resumen"], caja: true, reporte: function (FiltroEncomiendas $f, string $formato, string $quien) {
            $g = $this->consulta->guiaInterna($f);

            return $formato === "resumen" ? $g->resumen() : [$this->pdf->encomienda("guia_interna", ["g" => $g], $quien, "landscape"), self::nombre("guias_internas", $f)];
        });
    }

    /** `?desde=&hasta=&estacion=&empresa=`: facturas propias de encomiendas cobradas en el rango. */
    #[Route("/detalle-factura", name: "detalle_factura", methods: ["GET"])]
    public function detalleFactura(Request $request, #[CurrentUser] Usuario $usuario): Response
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->generar($request, $usuario, true, ["pdf", "xlsx", "resumen"], caja: true, reporte: function (FiltroEncomiendas $f, string $formato, string $quien) {
            $d = $this->consulta->detalleFactura($f);
            $nombre = self::nombre("detalle_factura_encomiendas", $f);

            return match ($formato) {
                "resumen" => $d->resumen(),
                "xlsx" => [$this->xlsx->facturaEncomiendas($d, $quien), $nombre],
                default => [$this->pdf->encomienda("detalle_factura", ["d" => $d], $quien, "landscape"), $nombre],
            };
        });
    }

    /** `?desde=&hasta=&empresa=&origen=&destino=&soloFacturado=&soloPorCobrar=`. */
    #[Route("/detalle-general", name: "detalle_general", methods: ["GET"])]
    public function detalleGeneral(Request $request, #[CurrentUser] Usuario $usuario): Response
    {
        $this->operacion();

        return $this->generar($request, $usuario, true, ["pdf", "xlsx", "resumen"], function (FiltroEncomiendas $f, string $formato, string $quien) {
            $d = $this->consulta->detalleGeneral($f);
            $nombre = self::nombre("detalle_general_encomiendas", $f);

            return match ($formato) {
                "resumen" => $d->resumen(),
                "xlsx" => [$this->xlsx->generalEncomiendas($d, $quien), $nombre],
                default => [$this->pdf->encomienda("detalle_general", ["d" => $d], $quien, "landscape"), $nombre],
            };
        }, conEstacion: false);
    }

    /** `?estacion=&empresa=&soloFacturado=&soloPorCobrar=`: las que no se han entregado. */
    #[Route("/pendientes-entrega", name: "pendientes_entrega", methods: ["GET"])]
    public function pendientesEntrega(Request $request, #[CurrentUser] Usuario $usuario): Response
    {
        $this->operacion();

        return $this->generar($request, $usuario, false, ["pdf", "resumen"], function (FiltroEncomiendas $f, string $formato, string $quien) {
            $p = $this->consulta->pendientesEntrega($f, $this->reloj->now());

            return $formato === "resumen" ? $p->resumen() : [$this->pdf->encomienda("pendientes_entrega", ["p" => $p], $quien, "landscape"), sprintf("encomiendas_pendientes_entrega_%s", $this->reloj->now()->format("Ymd"))];
        });
    }

    /** `?desde=&estacion=&empresa=`: cuadre del día. */
    #[Route("/cuadre", name: "cuadre", methods: ["GET"])]
    public function cuadre(Request $request, #[CurrentUser] Usuario $usuario): Response
    {
        $this->denyAccessUnlessGranted(self::VER);

        return $this->generar($request, $usuario, true, ["pdf", "resumen"], caja: true, reporte: function (FiltroEncomiendas $f, string $formato, string $quien) {
            $c = $this->consulta->cuadre($f);

            return $formato === "resumen" ? $c->resumen() : [$this->pdf->encomienda("cuadre", ["c" => $c], $quien, "portrait"), sprintf("cuadre_encomiendas_%s", $c->fecha->format("Ymd"))];
        });
    }

    private function operacion(): void
    {
        if (!$this->isGranted(self::VER) && !$this->isGranted(EncomiendaController::VER)) {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * Lee el filtro (con el alcance del usuario), valida el formato y entrega
     * el resultado: JSON para `resumen` o el archivo `[contenido, nombre]`.
     *
     * @param list<string> $formatos
     * @param callable(FiltroEncomiendas, string, string): (array<string, mixed>|array{0: string, 1: string}) $reporte
     */
    private function generar(Request $request, Usuario $usuario, bool $conFechas, array $formatos, callable $reporte, bool $conEstacion = true, bool $caja = false): Response
    {
        try {
            $formato = (string) $request->query->get("formato", "pdf");
            if (!in_array($formato, $formatos, true)) {
                throw new ReporteRechazado(sprintf("Formato no disponible; usa %s.", implode(", ", $formatos)), "formato_invalido", 400);
            }
            $alcance = $this->alcance($caja);
            $filtro = FiltroEncomiendas::desdeQuery($request->query->all(), $conFechas)
                ->conAlcance($conEstacion ? $alcance->estacionUnica() : null, $alcance->empresaUnica());
            $this->exigirDentro($alcance, $conEstacion ? $filtro->estacionId : null, $filtro->empresaId, $conEstacion);
            $resultado = $reporte($filtro, $formato, (string) $usuario->getUsername());
        } catch (ReporteRechazado $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        if ($formato === "resumen") {
            return $this->json($resultado);
        }
        [$contenido, $nombre] = $resultado;
        $tipo = $formato === "xlsx" ? self::XLSX : "application/pdf";

        return new Response($contenido, 200, [
            "Content-Type" => $tipo,
            "Content-Disposition" => sprintf('%s; filename="%s.%s"', $formato === "pdf" ? "inline" : "attachment", $nombre, $formato),
        ]);
    }

    /** Los de caja con `reporte.encomiendas`; los de operación, también con `encomienda.ver`. */
    private function alcance(bool $caja): Alcance
    {
        $deCaja = $this->autorizacion->alcance(Capacidad::REPORTE_ENCOMIENDAS);

        return $caja ? $deCaja : new Alcance([...$deCaja->ambitos(), ...$this->autorizacion->alcance(Capacidad::ENCOMIENDA_VER)->ambitos()]);
    }

    /** @throws ReporteRechazado */
    private function exigirDentro(Alcance $alcance, ?int $estacion, ?int $empresa, bool $conEstacion): void
    {
        if ($empresa === null && $alcance->empresas() !== null) {
            throw new ReporteRechazado("Elija una de sus empresas.", "alcance", 403);
        }
        if ($conEstacion && $estacion === null && $alcance->estaciones() !== null) {
            throw new ReporteRechazado("Elija una de sus estaciones.", "alcance", 403);
        }
        $donde = $empresa !== null ? Ubicacion::empresa($empresa) : Ubicacion::ninguna();
        if ($estacion !== null) {
            $donde = $donde->conEstacion($estacion);
        }
        if (!$alcance->contiene($donde)) {
            throw new ReporteRechazado("Fuera de su alcance: esa estación o empresa no es de las suyas.", "alcance", 403);
        }
    }

    private static function nombre(string $base, FiltroEncomiendas $f): string
    {
        return sprintf("%s_%s_%s", $base, $f->desde?->format("Ymd"), ($f->hasta ?? $f->desde)?->format("Ymd"));
    }
}
