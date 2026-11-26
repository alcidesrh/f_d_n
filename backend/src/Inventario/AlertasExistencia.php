<?php

declare(strict_types=1);

namespace App\Inventario;

use App\Afectacion\Afectaciones;
use App\Entity\Existencia;
use Psr\Log\LoggerInterface;

/**
 * El detector del faltante (ADR-071): tras cada cambio de una existencia
 * (un movimiento o sus niveles), si quedó debajo del mínimo crea la
 * afectación (una sola mientras siga abierta) y, si volvió al mínimo, la
 * resuelve como el sistema. El tipo es el del artículo o, si no dice, el
 * del almacén (`insumo_agotado` o `repuesto_faltante`): su `target` es
 * quien recibe el aviso del centinela.
 *
 * Corre después del commit: si falla, el movimiento ya quedó y el error se
 * registra en el log; el siguiente cambio de esa existencia lo vuelve a
 * intentar.
 */
final class AlertasExistencia
{
    public function __construct(
        private readonly Afectaciones $afectaciones,
        private readonly LoggerInterface $logger,
    ) {}

    public function revisar(Existencia $e): void
    {
        try {
            $clave = ReglasInventario::clave($e);
            if (!ReglasInventario::faltante($e->centesimas(), $e->minimoCentesimas())) {
                $this->afectaciones->resolverDetectada($clave, ReglasInventario::avisoRepuesto($e));

                return;
            }
            $almacen = $e->getAlmacen();
            $codigo = $e->getArticulo()->getTipoAfectacion()?->getCodigo() ?? $almacen->getTipo()->tipoAfectacionFaltante();
            $this->afectaciones->detectar($codigo, $clave, ReglasInventario::avisoFaltante($e), $almacen->getEmpresa(), $almacen->getEstacion(), $almacen->getBus());
        } catch (\Throwable $error) {
            $this->logger->error("No se pudo revisar el faltante de la existencia #{id}: {error}", ["id" => $e->getId(), "error" => $error->getMessage(), "exception" => $error]);
        }
    }
}
