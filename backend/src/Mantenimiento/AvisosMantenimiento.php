<?php

declare(strict_types=1);

namespace App\Mantenimiento;

use App\Acceso\Autorizacion;
use App\Acceso\Ubicacion;
use App\Chat\Conversaciones;
use App\Controller\MantenimientoController;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Avisa por el chat (canal del sistema) los servicios que entran en «por
 * vencer» o «vencido» (ADR-065), a quien registra órdenes de mantenimiento
 * en la empresa del bus. Cada servicio se avisa una vez por estado
 * (`aviso_mantenimiento`); al volver a estar al día se olvida, y el
 * siguiente vencimiento avisa otra vez.
 */
class AvisosMantenimiento
{
    public function __construct(
        private readonly Connection $db,
        private readonly Autorizacion $autorizacion,
        private readonly Conversaciones $chat,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<array<string, mixed>>                 $buses  de `EstadoFlota::buses`
     * @param list<array{id: int, nombre: string}>       $planes de `EstadoFlota::planes`
     *
     * @return array{nuevos: int, avisados: int}
     */
    public function avisar(array $buses, array $planes, \DateTimeImmutable $ahora): array
    {
        $avisados = [];
        foreach ($this->db->fetchAllAssociative("SELECT bus_id, plan_id, estado FROM aviso_mantenimiento") as $f) {
            $avisados["{$f["bus_id"]}:{$f["plan_id"]}"] = (string) $f["estado"];
        }
        $nombres = array_column($planes, "nombre", "id");
        ["nuevos" => $nuevos, "vigentes" => $vigentes] = self::nuevos($buses, $nombres, $avisados);

        foreach (array_diff_key($avisados, $vigentes) as $clave => $_) {
            [$bus, $plan] = explode(":", (string) $clave);
            $this->db->executeStatement("DELETE FROM aviso_mantenimiento WHERE bus_id = :bus AND plan_id = :plan", ["bus" => (int) $bus, "plan" => (int) $plan]);
        }
        foreach ($nuevos as $n) {
            $this->db->executeStatement(
                "INSERT INTO aviso_mantenimiento (bus_id, plan_id, estado, avisado_en) VALUES (:bus, :plan, :estado, :ahora)
                 ON CONFLICT (bus_id, plan_id) DO UPDATE SET estado = EXCLUDED.estado, avisado_en = EXCLUDED.avisado_en",
                ["bus" => $n["busId"], "plan" => $n["planId"], "estado" => $n["estado"], "ahora" => $ahora->format("Y-m-d H:i:s")],
            );
        }
        if ($nuevos === []) {
            return ["nuevos" => 0, "avisados" => 0];
        }

        $enviados = 0;
        foreach ($this->autorizacion->deTodos() as ["usuario" => $u, "permisos" => $p]) {
            $suyos = array_values(array_filter($nuevos, static fn(array $n) => $p->puede(MantenimientoController::EDITAR, Ubicacion::empresa($n["empresaId"]))));
            if ($suyos === []) {
                continue;
            }
            try {
                $this->chat->avisarSistema([$u], self::texto($suyos));
                $enviados++;
            } catch (\Throwable $e) {
                // Uno que falla no deja sin aviso a los demás.
                $this->logger->error("No se pudo avisar el mantenimiento a {usuario}: {error}", ["usuario" => $u->getUsername(), "error" => $e->getMessage(), "exception" => $e]);
            }
        }

        return ["nuevos" => count($nuevos), "avisados" => $enviados];
    }

    /**
     * Lo que toca avisar: lo que entra en «por vencer» o «vencido» y no se
     * había avisado en ese estado. Lo ya avisado como vencido no vuelve a
     * avisar por bajar a «por vencer» (un odómetro corregido). Puro.
     *
     * @param list<array<string, mixed>> $buses
     * @param array<int, string>         $nombres  plan → nombre
     * @param array<string, string>      $avisados `bus:plan` → estado ya avisado
     *
     * @return array{nuevos: list<array{busId: int, planId: int, empresaId: ?int, bus: string, plan: string, estado: string, detalle: string}>, vigentes: array<string, string>}
     */
    public static function nuevos(array $buses, array $nombres, array $avisados): array
    {
        $nuevos = [];
        $vigentes = [];
        foreach ($buses as $b) {
            foreach ($b["planes"] as $p) {
                if (!in_array($p["estado"], [ReglasMantenimiento::POR_VENCER, ReglasMantenimiento::VENCIDO], true)) {
                    continue;
                }
                $clave = "{$b["id"]}:{$p["plan"]}";
                $vigentes[$clave] = $p["estado"];
                $antes = $avisados[$clave] ?? null;
                if ($antes === $p["estado"] || $antes === ReglasMantenimiento::VENCIDO) {
                    continue;
                }
                $nuevos[] = [
                    "busId" => (int) $b["id"],
                    "planId" => (int) $p["plan"],
                    "empresaId" => $b["empresaId"],
                    "bus" => $b["codigo"] . ($b["matricula"] ? " ({$b["matricula"]})" : ""),
                    "plan" => $nombres[$p["plan"]] ?? "Plan #{$p["plan"]}",
                    "estado" => $p["estado"],
                    "detalle" => ReglasMantenimiento::detalle($p["estado"], $p["faltanKm"], $p["faltanDias"]),
                ];
            }
        }
        // Primero lo vencido.
        usort($nuevos, static fn(array $a, array $b) => [$a["estado"] !== ReglasMantenimiento::VENCIDO, $a["bus"], $a["plan"]] <=> [$b["estado"] !== ReglasMantenimiento::VENCIDO, $b["bus"], $b["plan"]]);

        return ["nuevos" => $nuevos, "vigentes" => $vigentes];
    }

    /** @param list<array{bus: string, plan: string, detalle: string}> $nuevos */
    public static function texto(array $nuevos): string
    {
        $titulo = count($nuevos) === 1 ? "Mantenimiento de flota: un servicio pide atención." : sprintf("Mantenimiento de flota: %d servicios piden atención.", count($nuevos));

        return implode("\n", [$titulo, ...array_map(static fn(array $n) => "• Bus {$n["bus"]}: {$n["plan"]}, {$n["detalle"]}.", $nuevos)]);
    }
}
