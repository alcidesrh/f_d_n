<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

/**
 * Puerto hacia el certificador de factura electrónica en línea (FEL de la
 * SAT). La implementación activa se elige en `config/services.yaml`
 * (`CertificadorSimulado` hasta tener la del certificador contratado).
 */
interface CertificadorFel
{
    /**
     * @throws CertificacionFallida si no se certificó (nada quedó emitido)
     */
    public function certificar(SolicitudDte $solicitud): DteCertificado;

    /**
     * Anula un DTE certificado. Si ya estaba anulado (p. ej. desde el portal
     * del certificador) no es un error: el DTE ya no está vigente.
     *
     * @throws CertificacionFallida si no se anuló (el DTE sigue vigente)
     */
    public function anular(SolicitudAnulacion $solicitud): void;
}
