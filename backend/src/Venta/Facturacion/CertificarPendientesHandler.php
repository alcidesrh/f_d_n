<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CertificarPendientesHandler
{
    public function __construct(private readonly CertificacionPendientes $certificacion) {}

    /** @return array{intentos: int, certificadas: int, errores: list<string>} */
    public function __invoke(CertificarPendientes $mensaje): array
    {
        return $this->certificacion->certificar();
    }
}
