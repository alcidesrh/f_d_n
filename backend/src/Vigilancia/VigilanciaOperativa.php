<?php

declare(strict_types=1);

namespace App\Vigilancia;

use App\Reporte\Diario\Entrega;
use App\Reporte\Diario\EntregaReportes;
use App\Reporte\Diario\ReporteProgramado;
use App\Reporte\Diario\Suscripciones;
use App\Tablero\ConsultaTablero;
use App\Tablero\Periodo;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * Vigilancia de la operación (ADR-044): cada media hora calcula las alertas
 * del tablero de hoy (las mismas reglas de `Tablero::alertas`) de cada
 * empresa que alguien vigila y avisa las nuevas a quien se suscribió a
 * «Alertas de la operación», recortadas a su alcance. Cada alerta se avisa
 * una vez por día y empresa (`aviso_operativo`); solo las que piden acción
 * (crítico y alerta), no las oportunidades (aviso).
 */
final class VigilanciaOperativa
{
    public const NIVELES = ["critico", "alerta"];
    private const NOMBRES = ["critico" => "Crítico", "alerta" => "Alerta"];

    public function __construct(
        private readonly Suscripciones $suscripciones,
        private readonly ConsultaTablero $tablero,
        private readonly EntregaReportes $entrega,
        private readonly Connection $db,
        private readonly ClockInterface $reloj,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return array{ambitos: int, nuevas: int, avisados: int} */
    public function vigilar(): array
    {
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());
        $this->db->executeStatement("DELETE FROM aviso_operativo WHERE dia < :limite", ["limite" => $ahora->modify("-7 days")->format("Y-m-d")]);
        $destinatarios = $this->suscripciones->destinatarios(ReporteProgramado::ALERTAS_OPERACION);
        if ($destinatarios === []) {
            return ["ambitos" => 0, "nuevas" => 0, "avisados" => 0];
        }

        $empresas = [];
        foreach ($destinatarios as ["celdas" => $celdas]) {
            foreach ($celdas as $c) {
                $empresas[self::ambito($c["empresa"])] = $c["empresa"];
            }
        }
        $nombres = array_column($this->tablero->empresas(array_values(array_filter($empresas))), "nombre", "id");
        $nuevas = [];
        foreach ($empresas as $ambito => $empresa) {
            $alertas = array_values(array_filter(
                $this->tablero->tablero(new Periodo(1, $ahora), $empresa)->alertas(),
                static fn(array $a) => in_array($a["nivel"], self::NIVELES, true),
            ));
            foreach ($alertas as $a) {
                if ($this->anotar($ambito, $a["codigo"], $ahora)) {
                    $nuevas[$ambito][] = $a + ["empresa" => $empresa === null ? null : ($nombres[$empresa] ?? "Empresa #{$empresa}")];
                }
            }
        }

        $avisados = 0;
        foreach ($destinatarios as ["usuario" => $u, "suscripcion" => $s, "celdas" => $celdas]) {
            $suyas = [];
            foreach ($celdas as $c) {
                array_push($suyas, ...($nuevas[self::ambito($c["empresa"])] ?? []));
            }
            if ($suyas === []) {
                continue;
            }
            try {
                $this->entrega->entregar($u, $s, ReporteProgramado::ALERTAS_OPERACION, null, $this->armar($suyas));
                $avisados++;
            } catch (\Throwable $e) {
                // Uno que falla (correo caído) no deja sin aviso a los demás.
                $this->logger->error("No se pudo avisar las alertas a {usuario}: {error}", ["usuario" => $u->getUsername(), "error" => $e->getMessage(), "exception" => $e]);
            }
        }

        return ["ambitos" => count($empresas), "nuevas" => array_sum(array_map("count", $nuevas)), "avisados" => $avisados];
    }

    /** @param list<array{nivel: string, titulo: string, detalle: string, empresa: ?string}> $alertas */
    private function armar(array $alertas): Entrega
    {
        $titulo = count($alertas) === 1 ? "Alerta de la operación" : sprintf("%d alertas de la operación", count($alertas));
        $filas = array_map(static fn(array $a) => [
            "nivel" => self::NOMBRES[$a["nivel"]] ?? $a["nivel"],
            "titulo" => ($a["empresa"] !== null ? "{$a["empresa"]}: " : "") . $a["titulo"],
            "detalle" => $a["detalle"],
        ], $alertas);
        $texto = implode("\n", [$titulo, ...array_map(static fn(array $f) => sprintf("• [%s] %s. %s", $f["nivel"], $f["titulo"], $f["detalle"]), $filas)]);

        return new Entrega($titulo, $texto, $this->twig->render("reporte/programado_alertas.html.twig", ["titulo" => $titulo, "alertas" => $filas]));
    }

    /** ¿Es nueva hoy? La anota (una sola vez por día, ámbito y código). */
    private function anotar(string $ambito, string $codigo, \DateTimeImmutable $ahora): bool
    {
        return $this->db->executeStatement(
            "INSERT INTO aviso_operativo (ambito, codigo, dia, avisado_en) VALUES (:ambito, :codigo, :dia, :ahora) ON CONFLICT DO NOTHING",
            ["ambito" => $ambito, "codigo" => $codigo, "dia" => $ahora->format("Y-m-d"), "ahora" => $ahora->format("Y-m-d H:i:s")],
        ) === 1;
    }

    private static function ambito(?int $empresa): string
    {
        return $empresa === null ? "todas" : "e:{$empresa}";
    }
}
