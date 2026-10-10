<?php

declare(strict_types=1);

namespace App\Venta\Acceso;

use App\Entity\BoletoVenta;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** Quién ve y quién opera sobre una venta. */
final class AccesoVentas
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $autorizacion,
    ) {}

    /** El vendedor, su agencia, o quien tenga permiso de lectura de ventas. */
    public function puedeVer(BoletoVenta $venta, Usuario $usuario): bool
    {
        $agencia = $usuario->getAgencia();
        if ($agencia !== null) {
            return $venta->getAgencia()?->getId() === $agencia->getId();
        }

        return $venta->getUsuario()?->getId() === $usuario->getId()
            || $this->autorizacion->isGranted("ROLE_ADMIN")
            || $this->autorizacion->isGranted("read", BoletoVenta::class);
    }

    /**
     * Sobre quién puede anular o reasignar (el permiso de la acción se
     * exige aparte): cualquier venta, salvo los usuarios de una agencia, que
     * solo operan las de su agencia.
     */
    public function puedeOperar(BoletoVenta $venta, Usuario $usuario): bool
    {
        $agencia = $usuario->getAgencia();

        return $agencia === null || $venta->getAgencia()?->getId() === $agencia->getId();
    }
}
