<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Sincronizacion\Legado\ApartadosLegado;
use App\Sincronizacion\Legado\ImportadorLegado;
use App\Sincronizacion\Nuevo\ExportadorCambios;
use Doctrine\DBAL\Connection;

/**
 * Una vuelta de la sincronización (ADR-042): trae lo que cambió en el legado,
 * lleva lo que quedó pendiente de aquí y limpia. Lo corre `app:sincronizar`
 * cada minuto. Con el interruptor apagado no hace nada.
 */
class Ciclo
{
    public function __construct(
        private readonly Interruptor $interruptor,
        private readonly ImportadorLegado $importador,
        private readonly ExportadorCambios $exportador,
        private readonly ApartadosLegado $apartados,
        private readonly Connection $db,
    ) {}

    /** @return array<string, mixed>|null el resumen, o null si está apagada */
    public function correr(): ?array
    {
        if (!$this->interruptor->activo()) {
            return null;
        }
        $resumen = [];
        $error = null;
        try {
            $resumen["legado"] = $this->importador->procesar();
            $resumen["nuevo"] = $this->exportador->procesar();
            $this->apartados->limpiar();
            $this->db->executeStatement("DELETE FROM sync_cambio WHERE procesado_en < NOW() - INTERVAL '7 days'");
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->importador->soltarConexiones();

            throw $e;
        } finally {
            $this->interruptor->anotarCiclo($resumen, $error);
        }

        return $resumen;
    }
}
