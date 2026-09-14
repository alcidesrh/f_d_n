<?php

declare(strict_types=1);

namespace App\Migration\Entidad;

use App\Migration\Job\Progreso;
use App\Migration\Migrador;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Migrador de salidas (itinerarios + boletos/asientos vendidos).
 *
 * Resuelve sus propias ramificaciones (empresa, trayecto, bus, cliente, ...)
 * internamente con dedupe por legacy_id. Al conjugarse con EjecutorEntidad se
 * ejecuta SOLO cuando no hay dependencias no resueltas previamente.
 *
 * Filtra las salidas pendientes (ya migradas fuera en PHP) para que la operación
 * "migrate N más" avance correctamente entre ejecuciones sucesivas.
 */
final class MigradorEntidadSalida implements MigradorEntidadInterface
{
    public function __construct(private readonly Migrador $migrador) {}

    public function nombre(): string
    {
        return "salida";
    }

    public function etiqueta(): string
    {
        return "Salidas (itinerarios + boletos)";
    }

    public function dependencias(): array
    {
        return [];
    }

    public function tablaFuente(): ?string
    {
        return "salida";
    }

    public function tablasDestino(): array
    {
        return ["itinerario", "boleto_asiento", "boleto_venta"];
    }

    public function soportaRangoFechas(): bool
    {
        return true;
    }

    public function soportaCantidad(): bool
    {
        return true;
    }

    public function totalFuente(Especificacion $es): int
    {
        return $this->migrador->contarSalidas($es->desde, $es->hasta);
    }

    public function migrar(
        Especificacion $es,
        ?OutputInterface $output,
        Progreso $progreso,
    ): array {
        $cantidad = $es->cantidad ?? 100;
        $progreso->informar(0, null, [], "Buscando salidas pendientes…");

        $pendientes = $this->migrador->fetchSalidasPendientes(
            $cantidad,
            $es->desde,
            $es->hasta,
        );
        $total = count($pendientes);
        $progreso->informar(0, $total);

        if (0 === $total) {
            if ($output) {
                $output->writeln(
                    "<comment>No quedan salidas pendientes en el rango indicado.</comment>",
                );
            }

            return [];
        }

        if ($output) {
            $output->writeln(
                sprintf("<info>Salidas pendientes a migrar: %d</info>", $total),
            );
        }

        $contadores = $this->migrador->migrarSalida(
            salidas: $cantidad,
            output: $output,
            onProgress: static function (int $done, int $t) use (
                $progreso,
                $total,
            ): void {
                $progreso->informar(min($done, $total), $total);
            },
            debeCancelar: static fn(): bool => $progreso->debeCancelar(),
            salidasPrefetchadas: $pendientes,
        );

        $progreso->informar($total, $total, $contadores, "Salidas migradas");

        return $contadores;
    }
}
