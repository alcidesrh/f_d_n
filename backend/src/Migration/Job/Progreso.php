<?php

declare(strict_types=1);

namespace App\Migration\Job;

/**
 * Acumula y persiste el progreso de un job de migración en su status.json,
 * y expone el flag de cancelación solicitado desde la UI.
 */
final class Progreso
{
    public function __construct(
        private readonly AlmacenDeJobs $almacen,
        private readonly string $jobId,
    ) {}

    public function id(): string
    {
        return $this->jobId;
    }

    /**
     * @param array<string, int> $contadores
     */
    public function informar(
        int $procesados,
        ?int $total = null,
        array $contadores = [],
        string $mensaje = "",
    ): void {
        $status = $this->almacen->leer($this->jobId) ?? [];
        $status["procesados"] = $procesados;
        if (null !== $total) {
            $status["total"] = $total;
        }
        if ([] !== $contadores) {
            $status["contadores"] = array_merge(
                (array) ($status["contadores"] ?? []),
                $contadores,
            );
        }
        if ("" !== $mensaje) {
            $status["mensaje"] = $mensaje;
        }
        $this->almacen->escribir($this->jobId, $status);
    }

    public function debeCancelar(): bool
    {
        return $this->almacen->cancelacionSolicitada($this->jobId);
    }
}
