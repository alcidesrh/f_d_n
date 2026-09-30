<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Venta\Facturacion\Forcon\CertificadorForcon;
use App\Venta\Facturacion\Forcon\ConsultaContribuyenteForcon;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Elige el certificador según `FEL_CERTIFICADOR`: `forcon` (real) o
 * `simulado` (por defecto; desarrollo y pruebas). Es el servicio que ven
 * `CertificadorFel` y `ConsultaContribuyente` (`config/services.yaml`).
 */
final class FelActivo implements CertificadorFel, ConsultaContribuyente
{
    public function __construct(
        #[Autowire(env: "default:fel_certificador_defecto:FEL_CERTIFICADOR")]
        private readonly string $certificador,
        private readonly CertificadorSimulado $simulado,
        private readonly ConsultaContribuyenteSimulada $consultaSimulada,
        private readonly CertificadorForcon $forcon,
        private readonly ConsultaContribuyenteForcon $consultaForcon,
    ) {}

    public function certificar(SolicitudDte $solicitud): DteCertificado
    {
        return $this->esForcon() ? $this->forcon->certificar($solicitud) : $this->simulado->certificar($solicitud);
    }

    public function nombreDeNit(string $nit): ?string
    {
        return $this->esForcon() ? $this->consultaForcon->nombreDeNit($nit) : $this->consultaSimulada->nombreDeNit($nit);
    }

    public function esForcon(): bool
    {
        return strtolower(trim($this->certificador)) === "forcon";
    }
}
