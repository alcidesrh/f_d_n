<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Venta\Facturacion\Forcon\AjustesForcon;
use App\Venta\Facturacion\Forcon\CertificadorForcon;
use App\Venta\Facturacion\Forcon\ConsultaContribuyenteForcon;

/**
 * Elige el certificador según `ConfiguracionForcon::activo`: Forcon (real) o
 * el simulado (por defecto; desarrollo y pruebas). Es el servicio que ven
 * `CertificadorFel` y `ConsultaContribuyente` (`config/services.yaml`).
 */
final class FelActivo implements CertificadorFel, ConsultaContribuyente
{
    public function __construct(
        private readonly AjustesForcon $ajustes,
        private readonly CertificadorSimulado $simulado,
        private readonly ConsultaContribuyenteSimulada $consultaSimulada,
        private readonly CertificadorForcon $forcon,
        private readonly ConsultaContribuyenteForcon $consultaForcon,
    ) {}

    public function certificar(SolicitudDte $solicitud): DteCertificado
    {
        return $this->esForcon() ? $this->forcon->certificar($solicitud) : $this->simulado->certificar($solicitud);
    }

    public function anular(SolicitudAnulacion $solicitud): void
    {
        $this->esForcon() ? $this->forcon->anular($solicitud) : $this->simulado->anular($solicitud);
    }

    public function nombreDeNit(string $nit): ?string
    {
        return $this->esForcon() ? $this->consultaForcon->nombreDeNit($nit) : $this->consultaSimulada->nombreDeNit($nit);
    }

    public function esForcon(): bool
    {
        return $this->ajustes->actual()->isActivo();
    }
}
