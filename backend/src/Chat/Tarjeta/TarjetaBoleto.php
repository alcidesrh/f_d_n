<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Entity\BoletoAsiento;
use App\Venta\Boleto\DatosBoleto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** Boleto (`BoletoAsiento`): estado, pasajero, asiento, trayecto, salida y venta. */
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
        return $this->auth->isGranted("read", BoletoAsiento::class);
    }

    public function resolver(array $ids): array
    {
        $datos = [];
        /** @var BoletoAsiento $b */
        foreach ($this->em->getRepository(BoletoAsiento::class)->findBy(["id" => $ids]) as $b) {
            $trayecto = $b->getTrayecto();
            $salida = $b->getSalida();
            $venta = $b->getBoletoVenta();
            $cliente = $b->getCliente();
            $datos[(int) $b->getId()] = [
                "titulo" => sprintf("Boleto %d", $b->getId()),
                "estado" => $b->getEstado()->value,
                "asiento" => $b->getAsiento() ? [
                    "numero" => $b->getAsiento()->getNumero(),
                    "clase" => $b->getAsiento()->getClase()->value,
                ] : null,
                "pasajero" => $cliente ? [
                    "nombre" => $cliente->getNombreCompleto(),
                    "documento" => $cliente->getNumeroDocumento(),
                ] : null,
                "trayecto" => $trayecto ? [
                    "origen" => $trayecto->getOrigen()?->getNombre(),
                    "destino" => $trayecto->getDestino()?->getNombre(),
                ] : null,
                "salida" => $salida ? [
                    "id" => $salida->getId(),
                    "fecha" => $salida->getFecha()?->format(DATE_ATOM),
                    "bus" => $salida->getBus()?->getCodigo(),
                ] : null,
                "precio" => DatosBoleto::importe($b->getPrecio()),
                "venta" => $venta ? [
                    "id" => $venta->getId(),
                    "canal" => $venta->getCanal()->value,
                    "fecha" => $venta->getCreada()?->format(DATE_ATOM),
                    "lugar" => $venta->getAgencia()?->getNombre() ?? $venta->getEstacion()?->getNombre(),
                    "vendedor" => $venta->getUsuario()?->getUsername(),
                    "sinCobro" => $venta->isCortesia() ? "cortesia" : ($venta->isVoucher() ? "voucher" : null),
                ] : null,
                "observacion" => $b->getObservacion(),
            ];
        }

        return $datos;
    }
}
