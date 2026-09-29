<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use App\Entity\Enum\EstadoRecorrido;
use App\Entity\Recorrido;
use App\Entity\Trayecto;
use App\Venta\Excepcion\AsientosNoDisponibles;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;
use Psr\Clock\ClockInterface;

/**
 * Reglas comunes a todos los canales: qué recorrido se puede vender, qué
 * trayecto, qué asientos y a qué precio.
 */
final class ReglasVenta
{
    /** La venta en línea cierra este tiempo antes de la salida (ADR-021). */
    public const CIERRE_WEB_MINUTOS = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Itinerarios $itinerarios,
        private readonly Disponibilidad $disponibilidad,
        private readonly ResolutorTarifa $tarifas,
        private readonly ClockInterface $reloj,
    ) {}

    /** Taquilla y agencias venden mientras el recorrido está programado o abordando. */
    public function exigirVendibleEnTaquilla(Recorrido $recorrido): void
    {
        if (!in_array($recorrido->getEstado(), [EstadoRecorrido::PROGRAMADA, EstadoRecorrido::ABORDANDO], true)) {
            throw new VentaRechazada(sprintf(
                "El recorrido está %s: ya no se venden boletos.",
                $recorrido->getEstado()->value,
            ));
        }
        $this->exigirBus($recorrido);
    }

    /** La web vende hasta `CIERRE_WEB_MINUTOS` antes de la salida y solo recorridos programados. */
    public function exigirVendibleEnLinea(Recorrido $recorrido): void
    {
        if ($recorrido->getEstado() !== EstadoRecorrido::PROGRAMADA) {
            throw new VentaRechazada("Este recorrido ya no está a la venta en línea.");
        }
        if ($this->reloj->now() >= $this->cierreEnLinea($recorrido)) {
            throw new VentaRechazada(sprintf(
                "La venta en línea cierra %d minutos antes de la salida. Compre su boleto en la estación.",
                self::CIERRE_WEB_MINUTOS,
            ), "venta_cerrada");
        }
        $this->exigirBus($recorrido);
    }

    public function cierreEnLinea(Recorrido $recorrido): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable($recorrido->getFecha())
            ->modify(sprintf("-%d minutes", self::CIERRE_WEB_MINUTOS));
    }

    /** Trayecto que viaja el cliente: el del recorrido o uno de sus subtrayectos. */
    public function trayecto(Recorrido $recorrido, ?int $trayectoId): Trayecto
    {
        if ($trayectoId === null || $trayectoId === $recorrido->getTrayecto()->getId()) {
            return $recorrido->getTrayecto();
        }
        if (!$this->itinerarios->deTrayecto($recorrido->getTrayecto())->contieneTrayecto($trayectoId)) {
            throw new VentaRechazada("El trayecto elegido no es parte del recorrido.");
        }

        return $this->em->find(Trayecto::class, $trayectoId)
            ?? throw new VentaRechazada("El trayecto elegido no existe.");
    }

    public function tramo(Recorrido $recorrido, Trayecto $trayecto): Tramo
    {
        return $this->itinerarios->deTrayecto($recorrido->getTrayecto())->tramo((int) $trayecto->getId())
            ?? throw new VentaRechazada("El trayecto elegido no es parte del recorrido.");
    }

    /**
     * Asientos del bus del recorrido, en el orden pedido.
     *
     * @param list<int> $ids
     *
     * @return list<Asiento>
     */
    public function asientos(Recorrido $recorrido, array $ids): array
    {
        if (count($ids) !== count(array_unique($ids))) {
            throw new VentaRechazada("Hay asientos repetidos en la venta.");
        }
        /** @var array<int, Asiento> $porId */
        $porId = [];
        foreach ($this->em->getRepository(Asiento::class)->findBy(["id" => $ids, "bus" => $recorrido->getBus()]) as $a) {
            $porId[$a->getId()] = $a;
        }
        $faltan = array_diff($ids, array_keys($porId));
        if ($faltan !== []) {
            throw new VentaRechazada("Algún asiento elegido no es del bus del recorrido.");
        }

        return array_map(static fn(int $id) => $porId[$id], $ids);
    }

    /**
     * @param list<Asiento> $asientos
     *
     * @throws AsientosNoDisponibles
     */
    public function exigirDisponibles(Recorrido $recorrido, Tramo $tramo, array $asientos, ?string $tokenPropio = null): void
    {
        $estados = $this->disponibilidad->estados($recorrido, $tramo, $tokenPropio);
        $ocupados = Ocupacion::noDisponibles(array_map(static fn(Asiento $a) => (int) $a->getId(), $asientos), $estados);
        if ($ocupados !== []) {
            $numeros = array_map(
                static fn(Asiento $a) => (int) $a->getNumero(),
                array_values(array_filter($asientos, static fn(Asiento $a) => in_array($a->getId(), $ocupados, true))),
            );
            throw AsientosNoDisponibles::numeros($numeros);
        }
    }

    /**
     * Precio de cada asiento. Se cobra la tarifa del trayecto que viaja, o la
     * del trayecto completo del recorrido si así se pide. Una cortesía vale 0.
     *
     * @param list<Asiento> $asientos
     */
    public function cotizar(
        Recorrido $recorrido,
        Trayecto $viaja,
        array $asientos,
        bool $cobrarTrayectoCompleto = false,
        bool $cortesia = false,
    ): Cotizacion {
        $trayectoTarifa = $cobrarTrayectoCompleto ? $recorrido->getTrayecto() : $viaja;
        $porClase = $this->tarifas->porClase(
            $recorrido,
            $trayectoTarifa,
            array_map(static fn(Asiento $a) => $a->getClase()->value, $asientos),
        );

        $lineas = [];
        $total = null;
        foreach ($asientos as $asiento) {
            $tarifa = $porClase[$asiento->getClase()->value] ?? null;
            if ($tarifa === null && !$cortesia) {
                throw new VentaRechazada(sprintf(
                    "No hay tarifa para asientos clase %s en %s - %s.",
                    $asiento->getClase()->value,
                    $trayectoTarifa->getOrigen()->getNombre(),
                    $trayectoTarifa->getDestino()->getNombre(),
                ), "sin_tarifa");
            }
            $moneda = $tarifa?->precio->getCurrency() ?? $total?->getCurrency() ?? new Currency("GTQ");
            $precio = $cortesia ? new Money(0, $moneda) : $tarifa->precio;
            if ($total !== null && !$total->isSameCurrency($precio)) {
                throw new VentaRechazada("Las tarifas de los asientos están en monedas distintas.");
            }
            $total = $total === null ? $precio : $total->add($precio);
            $lineas[] = ["asiento" => $asiento, "precio" => $precio, "tarifaId" => $tarifa?->id];
        }

        return new Cotizacion($lineas, $total ?? new Money(0, new Currency("GTQ")));
    }

    private function exigirBus(Recorrido $recorrido): void
    {
        if ($recorrido->getBus() === null) {
            throw new VentaRechazada("El recorrido todavía no tiene bus asignado.");
        }
    }
}
