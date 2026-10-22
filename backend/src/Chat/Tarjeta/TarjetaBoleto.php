<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Acceso\Capacidad;
use App\Acceso\Ubicador;
use App\Entity\BoletoAsiento;
use App\Salida\DetalleSalida;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Boleto (`BoletoAsiento`): estado, cliente, asiento, trayecto, salida, cuándo
 * y quién lo vendió. Solo los que el lector puede ver (`boleto.ver` donde cae
 * la venta, ADR-039).
 */
final class TarjetaBoleto implements Tarjeta
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $auth,
    ) {}

    public function tipo(): string
    {
        return "BoletoAsiento";
    }

    public function puedeVer(): bool
    {
        return $this->auth->isGranted(Capacidad::BOLETO_VER->value);
    }

    public function resolver(array $ids): array
    {
        $datos = [];
        /** @var BoletoAsiento $b */
        foreach ($this->em->getRepository(BoletoAsiento::class)->findBy(["id" => $ids]) as $b) {
            if ($b->getBoletoVenta() === null || !$this->auth->isGranted(Capacidad::BOLETO_VER->value, Ubicador::venta($b->getBoletoVenta()))) {
                continue;
            }
            $trayecto = $b->getTrayecto();
            $salida = $b->getSalida();
            $venta = $b->getBoletoVenta();
            $cliente = $b->getCliente();
            $datos[(int) $b->getId()] = [
                "titulo" => $trayecto
                    ? sprintf("%s → %s · asiento %s", $trayecto->getOrigen()?->getNombre(), $trayecto->getDestino()?->getNombre(), $b->getAsiento()?->getNumero() ?? "?")
                    : sprintf("Boleto %d", $b->getId()),
                "estado" => $b->getEstado()->value,
                "asiento" => $b->getAsiento()?->getNumero(),
                "cliente" => $cliente?->getNombreCompleto(),
                "trayecto" => $trayecto ? [
                    "origen" => $trayecto->getOrigen()?->getNombre(),
                    "destino" => $trayecto->getDestino()?->getNombre(),
                ] : null,
                "salida" => $salida ? ["id" => $salida->getId(), "fecha" => $salida->getFecha()?->format(DATE_ATOM)] : null,
                "creado" => $venta?->getCreada()?->format(DATE_ATOM),
                "vendedor" => DetalleSalida::quien($venta?->getUsuario()),
            ];
        }

        return $datos;
    }
}
