<?php

declare(strict_types=1);

namespace App\Venta\Facturacion\Forcon;

use App\Venta\Facturacion\CertificadorFel;
use App\Venta\Facturacion\DteCertificado;
use App\Venta\Facturacion\SolicitudAnulacion;
use App\Venta\Facturacion\SolicitudDte;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** Certificación de facturas con Forcon (`EmitirDteJson`, firma del emisor en Forcon). */
final class CertificadorForcon implements CertificadorFel
{
    public function __construct(
        private readonly ClienteForcon $cliente,
        private readonly DatosEmisorForcon $emisores,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function certificar(SolicitudDte $solicitud): DteCertificado
    {
        $emisor = $this->emisores->de($solicitud->emisorNit, $solicitud->establecimientoCodigo);

        return DteJsonForcon::respuesta(
            $this->cliente->post(ClienteForcon::EMITIR_JSON, DteJsonForcon::construir($solicitud, $emisor), $solicitud->emisorNit),
        );
    }

    public function anular(SolicitudAnulacion $solicitud): void
    {
        $anuladoAhora = DteJsonForcon::respuestaAnulacion(
            $this->cliente->post(ClienteForcon::ANULAR_JSON, DteJsonForcon::construirAnulacion($solicitud), $solicitud->emisorNit),
        );
        if (!$anuladoAhora) {
            $this->logger->warning("El DTE {uuid} ya estaba anulado en el certificador (anulado fuera del sistema).", ["uuid" => $solicitud->uuid]);
        }
    }
}
