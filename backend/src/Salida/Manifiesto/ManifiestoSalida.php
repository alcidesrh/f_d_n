<?php

declare(strict_types=1);

namespace App\Salida\Manifiesto;

use App\Entity\BoletoAsiento;
use App\Entity\Cliente;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Piloto;
use App\Entity\Salida;
use Doctrine\ORM\EntityManagerInterface;

/** Lee de la base los boletos vivos de una salida y arma su `Manifiesto`. */
final class ManifiestoSalida
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function de(Salida $salida): Manifiesto
    {
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->createQueryBuilder()
            ->select("b", "a", "v", "c", "vc", "t", "o", "d", "est", "ag", "f")
            ->from(BoletoAsiento::class, "b")
            ->join("b.asiento", "a")
            ->join("b.boletoVenta", "v")
            ->join("b.trayecto", "t")
            ->join("t.origen", "o")
            ->join("t.destino", "d")
            ->leftJoin("b.cliente", "c")
            ->leftJoin("v.cliente", "vc")
            ->leftJoin("v.estacion", "est")
            ->leftJoin("v.agencia", "ag")
            ->leftJoin("v.factura", "f")
            ->where("b.salida = :salida")
            ->andWhere("b.estado NOT IN (:libres)")
            ->setParameter("salida", $salida)
            ->setParameter("libres", [EstadoBoletoAsiento::ANULADO->value, EstadoBoletoAsiento::REASIGNADO->value])
            ->getQuery()
            ->getResult();

        $trayecto = $salida->getTrayecto();
        $bus = $salida->getBus();
        $empresa = $salida->getEmpresa();

        return new Manifiesto(
            (int) $salida->getId(),
            \DateTimeImmutable::createFromMutable($salida->getFecha()),
            (string) $trayecto->getOrigen()?->getNombre(),
            sprintf("%s - %s", $trayecto->getOrigen()?->getNombre() ?? "?", $trayecto->getDestino()?->getNombre() ?? "?"),
            $empresa?->getNombre() ?? "",
            $bus?->getCodigo(),
            [self::piloto($bus?->getPiloto()), self::piloto($bus?->getCopiloto())],
            array_map($this->pasajero(...), $boletos),
        );
    }

    private function pasajero(BoletoAsiento $b): Pasajero
    {
        $venta = $b->getBoletoVenta();
        $cliente = $b->getCliente() ?? $venta->getCliente();
        $factura = $venta->getFactura();
        $sinCobro = $venta->isVoucher() || $venta->isCortesia();
        $precio = $b->getPrecio();

        return new Pasajero(
            (int) $b->getId(),
            $cliente instanceof Cliente ? mb_strtoupper($cliente->getNombreCompleto()) : "—",
            $cliente?->getNacionalidad()?->getNombre(),
            match (true) {
                $venta->isVoucher() => "Voucher",
                $venta->isCortesia() => "Cortesía",
                $factura !== null => trim(sprintf("%s %s", $factura->getSerie(), $factura->getDte())),
                default => null,
            },
            $b->getAsiento()->getNumero(),
            $b->getAsiento()->getClase()->value,
            (string) $b->getTrayecto()->getOrigen()?->getNombre(),
            (string) $b->getTrayecto()->getDestino()?->getNombre(),
            $b->getEstado()->value,
            $venta->getCanal()->value,
            $precio === null ? 0 : (int) $precio->getAmount(),
            $precio?->getCurrency()->getCode() ?? "GTQ",
            $sinCobro,
            match (true) {
                $venta->getAgencia() !== null => sprintf("Agencia %s", $venta->getAgencia()->getNombre()),
                $venta->getCanal() === CanalVenta::WEB => "Página web",
                $venta->getEstacion() !== null => (string) $venta->getEstacion()->getNombre(),
                default => "—",
            },
            $b->getObservacion() ? trim($b->getObservacion()) : null,
        );
    }

    private static function piloto(?Piloto $p): string
    {
        if ($p === null) {
            return "N/D";
        }
        $apellido = $p->getApellido() !== "S/N" ? $p->getApellido() : null;
        $nombre = mb_strtoupper(trim(implode(" ", array_filter([$p->getNombre(), $apellido]))));

        return $nombre === "" ? $p->getCodigo() : sprintf("%s - %s", $p->getCodigo(), $nombre);
    }
}
