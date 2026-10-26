<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\Dispensa;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Venta\Clientes;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\ReglasBoletos;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Boletos con lo necesario para anularlos o reasignarlos (diálogos y
 * pantalla de reasignación): pasajero, viaje, venta y si todavía se puede
 * operar sobre ellos, con el motivo si no.
 */
final class ConsultaBoletos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * @param list<int>                   $ids
     * @param callable(BoletoVenta): bool $puedeOperar si el usuario puede operar sobre esa venta
     * @param callable(BoletoVenta): bool $puedeVer    si el usuario puede ver esa venta
     *
     * @return list<array<string, mixed>> en el orden pedido
     *
     * @throws VentaRechazada si algún boleto no existe o no se puede ver
     */
    public function de(array $ids, callable $puedeOperar, callable $puedeVer): array
    {
        $ids = array_values(array_unique($ids));
        /** @var array<int, BoletoAsiento> $porId */
        $porId = [];
        foreach ($this->em->getRepository(BoletoAsiento::class)->findBy(["id" => $ids]) as $b) {
            $porId[(int) $b->getId()] = $b;
        }
        if (count($porId) !== count($ids)) {
            throw new VentaRechazada("Algún boleto no existe.", "no_encontrado", 404);
        }

        foreach ($porId as $b) {
            if (!$puedeVer($b->getBoletoVenta())) {
                throw new VentaRechazada("No puede ver los boletos de esta venta.", "permiso", 403);
            }
        }

        return array_map(fn(int $id) => $this->fila($porId[$id], $puedeOperar), $ids);
    }

    /**
     * @param callable(BoletoVenta): bool $puedeOperar
     *
     * @return array<string, mixed>
     */
    private function fila(BoletoAsiento $b, callable $puedeOperar): array
    {
        $venta = $b->getBoletoVenta();
        $salida = $b->getSalida();
        $t = $b->getTrayecto();
        $cliente = $b->getCliente();

        $motivo = null;
        // Lo que lo impide pero se puede salvar con una autorización (ADR-040): sin el permiso o fuera de hora.
        $conAutorizacion = null;
        try {
            if (!$puedeOperar($venta)) {
                $conAutorizacion = Dispensa::PERMISO->value;
                throw new VentaRechazada("No puede operar sobre boletos de esta venta.");
            }
            if ($venta->getEstado() !== EstadoBoletoVenta::CONFIRMADA) {
                throw new VentaRechazada("La venta todavía se está procesando.");
            }
            ReglasBoletos::exigirOperable($b, $this->reloj->now(), "operar sobre el boleto");
        } catch (VentaRechazada $e) {
            $motivo = $e->getMessage();
            if ($conAutorizacion === null && $venta->getEstado() === EstadoBoletoVenta::CONFIRMADA && ReglasBoletos::soloPorHora($b, $this->reloj->now())) {
                $conAutorizacion = Dispensa::HORA->value;
            }
        }

        return [
            "id" => $b->getId(),
            "estado" => $b->getEstado()->value,
            "asiento" => ["id" => $b->getAsiento()->getId(), "numero" => $b->getAsiento()->getNumero(), "clase" => $b->getAsiento()->getClase()->value],
            "pasajero" => $cliente?->getNombreCompleto(),
            "precio" => DatosBoleto::importe($b->getPrecio()),
            "observacion" => $b->getObservacion(),
            "trayecto" => [
                "id" => $t->getId(),
                "origenId" => $t->getOrigen()->getId(),
                "destinoId" => $t->getDestino()->getId(),
                "origen" => (string) $t->getOrigen()->getNombre(),
                "destino" => (string) $t->getDestino()->getNombre(),
            ],
            "salida" => [
                "id" => $salida->getId(),
                "fecha" => $salida->getFecha()->format(DATE_ATOM),
                "estado" => $salida->getEstado()->value,
                "empresa" => $salida->getEmpresa() === null ? null : ["id" => $salida->getEmpresa()->getId(), "nombre" => $salida->getEmpresa()->getNombreCorto()],
                "bus" => $salida->getBus()?->getCodigo(),
            ],
            "venta" => [
                "id" => $venta->getId(),
                "canal" => $venta->getCanal()->value,
                "cortesia" => $venta->isCortesia(),
                "estadoFacturacion" => $venta->getEstadoFacturacion()->value,
                "factura" => $venta->getFactura() === null ? null : ["serie" => $venta->getFactura()->getSerie(), "numero" => $venta->getFactura()->getDte()],
                // Los que cubre su factura: con más de uno es conjunta (anular parte certifica otra con lo que quede).
                "boletosFactura" => array_map(static fn(BoletoAsiento $x) => (int) $x->getId(), ReglasBoletos::boletosDeLaFactura($venta)),
                "cliente" => $venta->getCliente() === null ? null : Clientes::fila($venta->getCliente()),
            ],
            "operable" => $motivo === null,
            "motivo" => $motivo,
            "conAutorizacion" => $conAutorizacion,
        ];
    }
}
