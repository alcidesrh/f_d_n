<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Asiento;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Entity\Trayecto;
use App\Venta\EnLinea\AjustesPagina;
use App\Venta\EnLinea\Recargo;
use App\Venta\Excepcion\AsientosNoDisponibles;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\Facturador;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;
use Psr\Clock\ClockInterface;

/**
 * Reglas comunes a todos los canales: qué salida se puede vender, qué
 * trayecto, qué asientos y a qué precio.
 */
final class ReglasVenta
{
    /**
     * Ninguna reserva web (precompra) sigue viva pasado este tiempo antes de
     * la salida (ADR-023). La venta en línea cierra antes: ver
     * `ConfiguracionPagina::cierreMinutos` (60 por defecto).
     */
    public const LIBERACION_MINUTOS = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Itinerarios $itinerarios,
        private readonly Disponibilidad $disponibilidad,
        private readonly ResolutorTarifa $tarifas,
        private readonly ClockInterface $reloj,
        private readonly AjustesPagina $ajustes,
    ) {}

    /** Taquilla y agencias venden mientras el salida está programado o abordando. */
    public function exigirVendibleEnTaquilla(Salida $salida): void
    {
        if (!in_array($salida->getEstado(), [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO], true)) {
            throw new VentaRechazada(sprintf(
                "El salida está %s: ya no se venden boletos.",
                $salida->getEstado()->value,
            ));
        }
        $this->exigirBus($salida);
    }

    /** La web vende solo salidas programadas, hasta su cierre en línea y si la venta en línea está activa. */
    public function exigirVendibleEnLinea(Salida $salida): void
    {
        if (!$this->ajustes->actual()->getVentaEnLinea()) {
            throw new VentaRechazada("La venta en línea está suspendida por el momento. Compre su boleto en la estación.", "venta_suspendida", 503);
        }
        if ($salida->getEstado() !== EstadoSalida::PROGRAMADA) {
            throw new VentaRechazada("Este salida ya no está a la venta en línea.");
        }
        if ($this->reloj->now() >= $this->cierreEnLinea($salida)) {
            throw new VentaRechazada(sprintf(
                "La venta en línea cierra %d minutos antes de la salida. Compre su boleto en la estación.",
                $this->ajustes->actual()->getCierreMinutos(),
            ), "venta_cerrada");
        }
        $this->exigirBus($salida);
    }

    public function cierreEnLinea(Salida $salida): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable($salida->getFecha())
            ->modify(sprintf("-%d minutes", $this->ajustes->actual()->getCierreMinutos()));
    }

    /** Hasta cuándo puede seguir apartado un asiento en la web (aunque el pago siga en curso). */
    public function limiteReservas(Salida $salida): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable($salida->getFecha())
            ->modify(sprintf("-%d minutes", self::LIBERACION_MINUTOS));
    }

    /** Trayecto que viaja el cliente: el del salida o uno de sus subtrayectos. */
    public function trayecto(Salida $salida, ?int $trayectoId): Trayecto
    {
        if ($trayectoId === null || $trayectoId === $salida->getTrayecto()->getId()) {
            return $salida->getTrayecto();
        }
        if (!$this->itinerarios->deTrayecto($salida->getTrayecto())->contieneTrayecto($trayectoId)) {
            throw new VentaRechazada("El trayecto elegido no es parte del salida.");
        }

        return $this->em->find(Trayecto::class, $trayectoId)
            ?? throw new VentaRechazada("El trayecto elegido no existe.");
    }

    public function tramo(Salida $salida, Trayecto $trayecto): Tramo
    {
        return $this->itinerarios->deTrayecto($salida->getTrayecto())->tramo((int) $trayecto->getId())
            ?? throw new VentaRechazada("El trayecto elegido no es parte del salida.");
    }

    /**
     * Asientos del bus del salida, en el orden pedido.
     *
     * @param list<int> $ids
     *
     * @return list<Asiento>
     */
    public function asientos(Salida $salida, array $ids): array
    {
        if (count($ids) !== count(array_unique($ids))) {
            throw new VentaRechazada("Hay asientos repetidos en la venta.");
        }
        /** @var array<int, Asiento> $porId */
        $porId = [];
        foreach ($this->em->getRepository(Asiento::class)->findBy(["id" => $ids, "bus" => $salida->getBus()]) as $a) {
            $porId[$a->getId()] = $a;
        }
        $faltan = array_diff($ids, array_keys($porId));
        if ($faltan !== []) {
            throw new VentaRechazada("Algún asiento elegido no es del bus del salida.");
        }

        return array_map(static fn(int $id) => $porId[$id], $ids);
    }

    /**
     * @param list<Asiento> $asientos
     *
     * @throws AsientosNoDisponibles
     */
    public function exigirDisponibles(Salida $salida, Tramo $tramo, array $asientos, ?string $tokenPropio = null): void
    {
        $estados = $this->disponibilidad->estados($salida, $tramo, $tokenPropio);
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
     * del trayecto completo del salida si así se pide. Una cortesía vale 0.
     *
     * Solo se vende un trayecto (o subtrayecto) con tarifa para la clase del
     * asiento, en todos los canales: también en una cortesía o cobrando el
     * trayecto completo, el tramo que viaja tiene que ser tarifable.
     *
     * @param list<Asiento> $asientos
     */
    public function cotizar(
        Salida $salida,
        Trayecto $viaja,
        array $asientos,
        bool $cobrarTrayectoCompleto = false,
        bool $cortesia = false,
    ): Cotizacion {
        $trayectoTarifa = $cobrarTrayectoCompleto ? $salida->getTrayecto() : $viaja;
        $tarifas = $this->tarifas->porTrayectos(
            $salida,
            [(int) $viaja->getId(), (int) $trayectoTarifa->getId()],
            array_map(static fn(Asiento $a) => $a->getClase()->value, $asientos),
        );
        $porClase = $tarifas[(int) $trayectoTarifa->getId()];

        $lineas = [];
        $total = null;
        foreach ($asientos as $asiento) {
            if (!isset($tarifas[(int) $viaja->getId()][$asiento->getClase()->value])) {
                throw new VentaRechazada(sprintf(
                    "%s - %s no se vende en asientos clase %s: no tiene tarifa.",
                    $viaja->getOrigen()->getNombre(),
                    $viaja->getDestino()->getNombre(),
                    $asiento->getClase()->value,
                ), "sin_tarifa");
            }
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

    /**
     * Precio en la página web: la tarifa más el recargo de la página
     * (`ConfiguracionPagina`), o el `$recargo` que se fijó al empezar el pago.
     *
     * @param list<Asiento> $asientos
     */
    public function cotizarEnLinea(Salida $salida, Trayecto $viaja, array $asientos, ?Recargo $recargo = null): Cotizacion
    {
        return $this->cotizar($salida, $viaja, $asientos)->conRecargo($recargo ?? $this->ajustes->recargo());
    }

    /**
     * La SAT no admite facturar a consumidor final (CF) desde Q2,500.00: se
     * valida antes de apartar o cobrar, no cuando ya falló la factura.
     */
    public function exigirReceptorFacturable(?string $nit, Money $total): void
    {
        if (
            Facturador::normalizarNit($nit) === "CF"
            && $total->getCurrency()->getCode() === "GTQ"
            && (int) $total->getAmount() >= Facturador::TOPE_CONSUMIDOR_FINAL
        ) {
            throw new VentaRechazada(
                "Para compras de Q2,500.00 o más la SAT exige el NIT del cliente: no se puede facturar a CF.",
                "requiere_nit",
            );
        }
    }

    private function exigirBus(Salida $salida): void
    {
        if ($salida->getBus() === null) {
            throw new VentaRechazada("El salida todavía no tiene bus asignado.");
        }
    }
}
