<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Reporte\ConsultaReportes;
use App\Reporte\Dinero;
use App\Reporte\Encomienda\ConsultaReportesEncomienda;
use App\Reporte\Encomienda\CuadreEncomiendas;
use App\Reporte\Encomienda\FiltroEncomiendas;
use App\Reporte\FiltroCuadre;
use App\Reporte\ReportePdf;
use App\Tablero\ConsultaTablero;
use App\Tablero\Periodo;
use Twig\Environment;

/**
 * Arma los reportes diarios de un destinatario (ADR-044) con las mismas
 * consultas que las pantallas, recortadas a sus celdas (`Celdas`): nunca ve
 * en un reporte lo que no ve en la pantalla. Los PDF son los de siempre.
 * Null: no hay nada que mandarle (todo salió vacío).
 */
final class GeneradorReportes
{
    /** Tope de PDF por mensaje (un gerente con muchas estaciones). */
    public const MAX_ARCHIVOS = 12;

    /** Quién figura como "generado por" en los PDF. */
    public const AUTOR = "Programador de tareas";

    public function __construct(
        private readonly ConsultaTablero $tablero,
        private readonly ConsultaIncidencias $incidencias,
        private readonly ConsultaReportes $reportes,
        private readonly ConsultaReportesEncomienda $encomiendas,
        private readonly ReportePdf $pdf,
        private readonly Environment $twig,
    ) {}

    /** @param list<array{empresa: ?int, estacion: ?int}> $celdas */
    public function generar(ReporteProgramado $reporte, \DateTimeImmutable $dia, array $celdas): ?Entrega
    {
        return match ($reporte) {
            ReporteProgramado::RESUMEN_DIARIO => $this->resumen($dia, $celdas),
            ReporteProgramado::CUADRE_BOLETOS => $this->cuadresBoletos($dia, $celdas),
            ReporteProgramado::CUADRE_ENCOMIENDAS => $this->cuadresEncomiendas($dia, $celdas),
            ReporteProgramado::ALERTAS_OPERACION => null,
        };
    }

    /** @param list<array{empresa: ?int, estacion: ?int}> $celdas */
    private function resumen(\DateTimeImmutable $dia, array $celdas): ?Entrega
    {
        $nombres = $this->nombresEmpresas($celdas);
        $secciones = [];
        foreach ($celdas as $c) {
            $t = $this->tablero->tablero(new Periodo(1, $dia->setTime(23, 59, 59)), $c["empresa"]);
            $seccion = ResumenDiario::seccion($c["empresa"] === null ? "Todas las empresas" : ($nombres[$c["empresa"]] ?? "Empresa #" . $c["empresa"]), $t, $this->incidencias->porUsuario($dia, $c["empresa"]));
            if ($seccion !== null) {
                $secciones[] = $seccion;
            }
        }
        if ($secciones === []) {
            return null;
        }
        $titulo = sprintf("Resumen del %s", ResumenDiario::fechaLarga($dia));

        return new Entrega(
            $titulo,
            ResumenDiario::texto($titulo, $secciones),
            $this->twig->render("reporte/programado_resumen.html.twig", ["titulo" => $titulo, "secciones" => $secciones]),
        );
    }

    /** @param list<array{empresa: ?int, estacion: ?int}> $celdas */
    private function cuadresBoletos(\DateTimeImmutable $dia, array $celdas): ?Entrega
    {
        $lineas = [];
        $archivos = [];
        foreach ($celdas as $c) {
            foreach ($this->reportes->monedas() as $moneda) {
                $cuadre = $this->reportes->cuadre(new FiltroCuadre($dia, $c["estacion"], $c["empresa"], $moneda["sigla"]), Celdas::alcanceDe($c));
                if ($cuadre->totalUsuarios["boletos"] === 0 && $cuadre->prepagadosOtras === []) {
                    continue;
                }
                $donde = self::donde($cuadre->estacion, $cuadre->empresa);
                $lineas[] = sprintf(
                    "%s: %d boletos, recibido %s, anulado %s.",
                    $donde,
                    $cuadre->totalUsuarios["boletos"],
                    Dinero::con($cuadre->totalUsuarios["recibido"], $cuadre->moneda),
                    Dinero::con($cuadre->totalUsuarios["anulado"], $cuadre->moneda),
                );
                $archivos[] = [
                    "nombre" => sprintf("cuadre_venta_boletos_%s_%s_%s.pdf", $dia->format("Ymd"), self::slug($donde), strtolower($cuadre->moneda)),
                    "contenido" => $this->pdf->cuadre($cuadre, self::AUTOR),
                ];
            }
        }

        return $this->conArchivos(sprintf("Cuadre de venta de boletos del %s", $dia->format("d/m/Y")), $lineas, $archivos);
    }

    /** @param list<array{empresa: ?int, estacion: ?int}> $celdas */
    private function cuadresEncomiendas(\DateTimeImmutable $dia, array $celdas): ?Entrega
    {
        $lineas = [];
        $archivos = [];
        foreach ($celdas as $c) {
            $cuadre = $this->encomiendas->cuadre(new FiltroEncomiendas($dia, $dia, $c["estacion"], $c["empresa"]));
            if (self::encomiendasVacio($cuadre)) {
                continue;
            }
            $donde = self::donde($cuadre->estacion, $cuadre->empresa);
            $t = $cuadre->totalUsuarios;
            $lineas[] = sprintf("%s: %d recibidas, %d enviadas, ingreso %s.", $donde, $t["facturadas"] + $t["porCobrarCantidad"] + $t["internas"], $cuadre->totalSalidas["cantidad"], Dinero::con($cuadre->ingreso(), ConsultaTablero::MONEDA));
            $archivos[] = [
                "nombre" => sprintf("cuadre_encomiendas_%s_%s.pdf", $dia->format("Ymd"), self::slug($donde)),
                "contenido" => $this->pdf->encomienda("cuadre", ["c" => $cuadre], self::AUTOR, "portrait"),
            ];
        }

        return $this->conArchivos(sprintf("Cuadre de encomiendas del %s", $dia->format("d/m/Y")), $lineas, $archivos);
    }

    /**
     * @param list<string>                                     $lineas
     * @param list<array{nombre: string, contenido: string}> $archivos
     */
    private function conArchivos(string $titulo, array $lineas, array $archivos): ?Entrega
    {
        if ($archivos === []) {
            return null;
        }
        $resto = count($archivos) - self::MAX_ARCHIVOS;
        $archivos = array_slice($archivos, 0, self::MAX_ARCHIVOS);
        $nota = $resto > 0 ? sprintf("Hay %d más: los demás se sacan desde Reportes.", $resto) : null;
        $texto = implode("\n", array_filter([$titulo, ...array_map(static fn(string $l) => "• " . $l, $lineas), $nota]));

        return new Entrega(
            $titulo,
            $texto,
            $this->twig->render("reporte/programado_archivos.html.twig", ["titulo" => $titulo, "lineas" => $lineas, "nota" => $nota]),
            $archivos,
        );
    }

    /**
     * @param list<array{empresa: ?int, estacion: ?int}> $celdas
     *
     * @return array<int, string>
     */
    private function nombresEmpresas(array $celdas): array
    {
        $ids = array_values(array_filter(array_column($celdas, "empresa")));
        if ($ids === []) {
            return [];
        }

        return array_column($this->tablero->empresas($ids), "nombre", "id");
    }

    private static function encomiendasVacio(CuadreEncomiendas $c): bool
    {
        return $c->usuarios === [] && $c->salidas === [] && $c->cobradas === [] && $c->anuladas === [];
    }

    private static function donde(?string $estacion, ?string $empresa): string
    {
        return match (true) {
            $estacion !== null && $empresa !== null => "{$estacion} ({$empresa})",
            $estacion !== null => $estacion,
            $empresa !== null => "{$empresa}, todas las estaciones",
            default => "Todas las estaciones",
        };
    }

    private static function slug(string $texto): string
    {
        $ascii = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $texto) ?: $texto;

        return trim((string) preg_replace('/[^a-z0-9]+/', "_", strtolower($ascii)), "_") ?: "reporte";
    }
}
