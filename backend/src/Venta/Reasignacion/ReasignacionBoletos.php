<?php

declare(strict_types=1);

namespace App\Venta\Reasignacion;

use App\Bitacora\RegistradorBitacora;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\PagoWeb;
use App\Entity\Salida;
use App\Venta\EnLinea\Recargo;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\PublicadorOcupacion;
use App\Venta\ReglasBoletos;
use App\Venta\ReglasVenta;
use App\Venta\Transaccion;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Cambio de asiento (y de salida) de boletos emitidos, antes de la hora de
 * salida. No cobra ni factura: el nuevo asiento debe costar lo mismo que el
 * boleto original, y el boleto nuevo queda en la misma venta (con su factura).
 * El original pasa a `reasignado` y el asiento que ocupaba queda libre.
 *
 * Todo en una transacción con las salidas implicadas bloqueadas: si algo
 * falla (asiento tomado, precio distinto…) no cambia nada.
 */
final class ReasignacionBoletos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly ReglasVenta $reglas,
        private readonly PublicadorOcupacion $publicador,
        private readonly RegistradorBitacora $bitacora,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * @param callable(BoletoVenta): bool $puedeOperar si el usuario puede operar sobre esa venta
     *
     * @return array{venta: BoletoVenta, nuevos: list<int>} la venta y los ids de los boletos nuevos
     *
     * @throws VentaRechazada
     */
    public function reasignar(SolicitudReasignacion $s, callable $puedeOperar): array
    {
        $resultado = $this->transaccion->ejecutar(fn() => $this->aplicar($s, $puedeOperar));

        foreach ($resultado["salidas"] as $salidaId) {
            $this->publicador->cambio($salidaId);
        }
        $venta = $this->em->find(BoletoVenta::class, $resultado["venta"]);

        return ["venta" => $venta, "nuevos" => $resultado["nuevos"]];
    }

    /**
     * @param callable(BoletoVenta): bool $puedeOperar
     *
     * @return array{venta: int, nuevos: list<int>, salidas: list<int>}
     */
    private function aplicar(SolicitudReasignacion $s, callable $puedeOperar): array
    {
        /** @var array<int, BoletoAsiento> $porId */
        $porId = [];
        foreach ($this->em->getRepository(BoletoAsiento::class)->findBy(["id" => $s->boletos]) as $b) {
            $porId[(int) $b->getId()] = $b;
        }
        if (count($porId) !== count($s->boletos)) {
            throw new VentaRechazada("Algún boleto no existe.", "no_encontrado", 404);
        }
        /** @var list<BoletoAsiento> $viejos en el orden pedido */
        $viejos = array_map(static fn(int $id) => $porId[$id], $s->boletos);

        $venta = $viejos[0]->getBoletoVenta();
        foreach ($viejos as $b) {
            if ($b->getBoletoVenta()->getId() !== $venta->getId()) {
                throw new VentaRechazada("Los boletos de una reasignación deben ser de una misma venta.", "ventas_distintas");
            }
        }
        if (!$puedeOperar($venta)) {
            throw new VentaRechazada("No puede reasignar boletos de esta venta.", "permiso", 403);
        }
        if ($venta->getEstado() !== EstadoBoletoVenta::CONFIRMADA) {
            throw new VentaRechazada("La venta todavía se está procesando.", "venta_en_proceso", 409);
        }

        $salidaIds = array_map(static fn(BoletoAsiento $b) => (int) $b->getSalida()->getId(), $viejos);
        $salidaIds[] = $s->salidaId;
        $salidaIds = array_values(array_unique($salidaIds));
        sort($salidaIds);
        foreach ($salidaIds as $id) {
            $this->em->find(Salida::class, $id, LockMode::PESSIMISTIC_WRITE);
        }
        $destino = $this->em->find(Salida::class, $s->salidaId)
            ?? throw new VentaRechazada("La salida no existe.", "no_encontrado", 404);

        $ahora = $this->reloj->now();
        foreach ($viejos as $b) {
            $this->em->refresh($b);
            ReglasBoletos::exigirEmitido($b);
            ReglasBoletos::exigirAntesDeSalir($b->getSalida(), $ahora, "reasignar el boleto");
            ReglasBoletos::exigirEmpresaCompatible($b->getSalida(), $destino, $venta);
        }
        $this->reglas->exigirVendibleEnTaquilla($destino);
        ReglasBoletos::exigirAntesDeSalir($destino, $ahora, "reasignar a esa salida");

        $trayecto = $this->reglas->trayecto($destino, $s->trayectoId);
        $tramo = $this->reglas->tramo($destino, $trayecto);
        $asientos = $this->reglas->asientos($destino, $s->asientos);
        foreach ($viejos as $i => $b) {
            if (
                $b->getSalida()->getId() === $destino->getId()
                && $b->getTrayecto()->getId() === $trayecto->getId()
                && $b->getAsiento()->getId() === $asientos[$i]->getId()
            ) {
                throw new VentaRechazada(sprintf("El boleto del asiento %s ya está en ese asiento.", $b->getAsiento()->getNumero()), "sin_cambio");
            }
        }

        // Los asientos actuales se liberan primero: se puede pasar a uno que hoy ocupa otro boleto de la selección.
        foreach ($viejos as $i => $b) {
            $this->bitacora->anotar($b, ["haciaSalida" => $destino->getId(), "haciaAsiento" => $asientos[$i]->getNumero()]);
            $b->setEstado(EstadoBoletoAsiento::REASIGNADO);
        }
        $this->em->flush();

        $this->reglas->exigirDisponibles($destino, $tramo, $asientos);
        $cotizacion = $this->reglas
            ->cotizar($destino, $trayecto, $asientos, $s->cobrarTrayectoCompleto, $venta->isCortesia())
            ->conRecargo($this->recargoDe($venta));

        $nuevos = [];
        foreach ($viejos as $i => $b) {
            ReglasBoletos::exigirMismoPrecio($b, $cotizacion->precioDe($asientos[$i]), $asientos[$i]->getNumero());
            $nuevo = (new BoletoAsiento())
                ->setAsiento($asientos[$i])
                ->setTrayecto($trayecto)
                ->setSalida($destino)
                ->setCliente($b->getCliente())
                ->setPrecio($b->getPrecio())
                ->setObservacion($b->getObservacion())
                ->setReasignadoDe($b);
            $venta->addAsiento($nuevo);
            $this->em->persist($nuevo);
            $nuevos[] = $nuevo;
        }
        $this->em->flush();

        return [
            "venta" => (int) $venta->getId(),
            "nuevos" => array_map(static fn(BoletoAsiento $n) => (int) $n->getId(), $nuevos),
            "salidas" => $salidaIds,
        ];
    }

    /** Lo vendido en la página lleva el recargo con el que se pagó; lo demás, ninguno. */
    public function recargoDe(BoletoVenta $venta): Recargo
    {
        if ($venta->getCanal() !== CanalVenta::WEB) {
            return Recargo::ninguno();
        }
        /** @var PagoWeb|null $pago */
        $pago = $this->em->createQueryBuilder()
            ->select("p")
            ->from(PagoWeb::class, "p")
            ->where("p.boletoVenta = :venta OR p.boletoVentaRegreso = :venta")
            ->setParameter("venta", $venta)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $pago === null ? Recargo::ninguno() : Recargo::de($pago->getRecargoPorciento());
    }
}
