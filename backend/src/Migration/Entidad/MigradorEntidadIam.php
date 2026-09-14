<?php

declare(strict_types=1);

namespace App\Migration\Entidad;

use App\Migration\Job\Progreso;
use App\Migration\MigradorIAM;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Migrador del bloque IAM (acciones, permisos y roles base).
 * Idempotente; no aplica rango de fechas ni cantidad.
 */
final class MigradorEntidadIam implements MigradorEntidadInterface
{
    public function __construct(private readonly MigradorIAM $iam) {}

    public function nombre(): string
    {
        return "iam";
    }

    public function etiqueta(): string
    {
        return "IAM (acciones, permisos, roles)";
    }

    public function dependencias(): array
    {
        return ["usuario"];
    }

    public function tablaFuente(): ?string
    {
        return "custom_rol";
    }

    public function tablasDestino(): array
    {
        return ["action", "permiso", "role"];
    }

    public function soportaRangoFechas(): bool
    {
        return false;
    }

    public function soportaCantidad(): bool
    {
        return false;
    }

    public function totalFuente(Especificacion $es): int
    {
        return -1;
    }

    public function migrar(
        Especificacion $es,
        ?OutputInterface $output,
        Progreso $progreso,
    ): array {
        $progreso->informar(
            0,
            1,
            [],
            "Migrando IAM (acciones, permisos, roles)…",
        );
        $contadores = $this->iam->migrar($output);
        $progreso->informar(1, 1, $contadores, "IAM migrado");

        return $contadores;
    }
}
