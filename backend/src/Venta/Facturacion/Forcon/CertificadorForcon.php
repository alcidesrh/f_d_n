<?php

declare(strict_types=1);

namespace App\Venta\Facturacion\Forcon;

use App\Venta\Facturacion\CertificadorFel;
use App\Venta\Facturacion\DteCertificado;
use App\Venta\Facturacion\SolicitudDte;

/** Certificación de facturas con Forcon (`EmitirDteJson`, firma del emisor en Forcon). */
final class CertificadorForcon implements CertificadorFel
{
    public function __construct(
        private readonly ClienteForcon $cliente,
        private readonly DatosEmisorForcon $emisores,
    ) {}

    public function certificar(SolicitudDte $solicitud): DteCertificado
    {
        $emisor = $this->emisores->de($solicitud->emisorNit, $solicitud->establecimientoCodigo);

        return DteJsonForcon::respuesta(
            $this->cliente->post(ClienteForcon::EMITIR_JSON, DteJsonForcon::construir($solicitud, $emisor), $solicitud->emisorNit),
        );
    }
}
