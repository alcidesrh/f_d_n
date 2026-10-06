<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\ReservaAsiento;
use App\Entity\Salida;
use App\Venta\Boleto\DatosBoleto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Qué hay detrás de un asiento ocupado del croquis (solo personal): los
 * boletos vivos del asiento (uno por tramo vendido) con pasajero, venta,
 * cobro y factura agrupados, o la reserva web vigente. `completo = false`
 * (venta de otro vendedor a quien no puede verla) deja solo lo operativo:
 * tramo, estado y canal; sin datos del pasajero, importes ni factura.
 */
final class DetalleAsiento
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * @param callable(BoletoVenta): bool $puedeVerVenta
     * @return array<string, mixed>
     */
    public function de(Salida $salida, Asiento $asiento, callable $puedeVerVenta): array
    {
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->createQueryBuilder()
            ->select("b", "v")
            ->from(BoletoAsiento::class, "b")
            ->join("b.boletoVenta", "v")
            ->where("b.salida = :salida")
            ->andWhere("b.asiento = :asiento")
            ->andWhere("b.estado NOT IN (:libres)")
            ->setParameter("salida", $salida)
            ->setParameter("asiento", $asiento)
            ->setParameter("libres", [EstadoBoletoAsiento::ANULADO->value, EstadoBoletoAsiento::REASIGNADO->value])
            ->orderBy("b.id")
            ->getQuery()
            ->getResult();

        $reserva = $boletos !== [] ? null : $this->em->createQueryBuilder()
            ->select("r")
            ->from(ReservaAsiento::class, "r")
            ->where("r.salida = :salida")
            ->andWhere("r.asiento = :asiento")
            ->andWhere("r.expiraEn > :ahora")
            ->setParameter("salida", $salida)
            ->setParameter("asiento", $asiento)
            ->setParameter("ahora", $this->reloj->now())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            "asiento" => ["id" => $asiento->getId(), "numero" => $asiento->getNumero(), "clase" => $asiento->getClase()->value],
            "reserva" => $reserva === null ? null : [
                "creada" => $reserva->getCreadaEn()->format(DATE_ATOM),
                "expiraEn" => $reserva->getExpiraEn()->format(DATE_ATOM),
                "trayecto" => self::trayecto($reserva->getTrayecto()),
            ],
            "boletos" => array_map(fn(BoletoAsiento $b) => $this->boleto($b, $puedeVerVenta($b->getBoletoVenta())), $boletos),
        ];
    }

    /** @return array<string, mixed> */
    private function boleto(BoletoAsiento $b, bool $completo): array
    {
        $venta = $b->getBoletoVenta();
        $base = [
            "id" => $b->getId(),
            "completo" => $completo,
            "estado" => $b->getEstado()->value,
            "trayecto" => self::trayecto($b->getTrayecto()),
            "venta" => [
                "id" => $venta->getId(),
                "canal" => $venta->getCanal()->value,
                "cortesia" => $venta->isCortesia(),
                "voucher" => $venta->isVoucher(),
            ],
        ];
        if (!$completo) {
            return $base;
        }

        $cliente = $b->getCliente();
        $factura = $venta->getFactura();

        return [
            ...$base,
            "pasajero" => $cliente === null ? null : [
                "nombre" => $cliente->getNombreCompleto(),
                "documento" => $cliente->getNumeroDocumento(),
                "tipoDocumento" => $cliente->getTipoDocumento()?->getNombre(),
                "nacionalidad" => $cliente->getNacionalidad()?->getNombre(),
                "telefono" => $cliente->getTelefono(),
                "email" => $cliente->getEmail(),
            ],
            "observacion" => $b->getObservacion(),
            "precio" => DatosBoleto::importe($b->getPrecio()),
            "venta" => [
                ...$base["venta"],
                "creada" => $venta->getCreada()?->format(DATE_ATOM),
                "vendedor" => $venta->getUsuario()?->getUsername(),
                "estacion" => $venta->getEstacion()?->getNombre(),
                "agencia" => $venta->getAgencia()?->getNombre(),
                "tipoPago" => $venta->getTipoPago()?->getNombre(),
                "total" => DatosBoleto::importe($venta->getTotal()),
                "referenciaPago" => $venta->getReferenciaPago(),
                "comprador" => $venta->getCliente()?->getNombreCompleto(),
                "estadoFacturacion" => $venta->getEstadoFacturacion()->value,
                "factura" => $factura === null ? null : [
                    "serie" => $factura->getSerie(),
                    "numero" => $factura->getDte(),
                    "nit" => $factura->getReceptopNit(),
                    "nombre" => $factura->getReceptorNombre(),
                    "urlPdf" => $factura->getUrlPdf(),
                ],
            ],
        ];
    }

    /** @return array{id: ?int, origen: string, destino: string} */
    private static function trayecto(\App\Entity\Trayecto $t): array
    {
        return ["id" => $t->getId(), "origen" => (string) $t->getOrigen()->getNombre(), "destino" => (string) $t->getDestino()->getNombre()];
    }
}
