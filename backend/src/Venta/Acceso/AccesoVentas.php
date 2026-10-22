<?php

declare(strict_types=1);

namespace App\Venta\Acceso;

use App\Acceso\Autorizacion;
use App\Acceso\Capacidad;
use App\Acceso\Ubicador;
use App\Entity\BoletoVenta;
use App\Entity\Usuario;

/**
 * Quién ve y quién opera sobre una venta (ADR-039): la capacidad donde cae
 * la venta —la empresa de su salida, la estación donde se vendió y su
 * agencia—. Un usuario de agencia solo alcanza las de la suya.
 */
final class AccesoVentas
{
    public function __construct(
        private readonly Autorizacion $autorizacion,
    ) {}

    /** Quien la vendió, o quien tiene `boleto.ver` donde cae. */
    public function puedeVer(BoletoVenta $venta, Usuario $usuario): bool
    {
        return ($venta->getUsuario()?->getId() === $usuario->getId() && $usuario->getId() !== null)
            || $this->puedeOperar($venta, $usuario, Capacidad::BOLETO_VER);
    }

    /** Tiene esa capacidad (anular, reasignar, cancelar…) donde cae la venta. */
    public function puedeOperar(BoletoVenta $venta, Usuario $usuario, Capacidad $capacidad): bool
    {
        return $this->autorizacion->de($usuario)->puede($capacidad->value, Ubicador::venta($venta));
    }
}
