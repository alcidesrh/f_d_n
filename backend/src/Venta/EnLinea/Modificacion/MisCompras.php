<?php

declare(strict_types=1);

namespace App\Venta\EnLinea\Modificacion;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoPagoWeb;
use App\Entity\Enum\TransicionBoleto;
use App\Entity\PagoWeb;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Boleto\Flujo\FlujoBoleto;
use App\Venta\EnLinea\AjustesPagina;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\HorasSalida;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * «Mis compras» en la página: los viajes comprados en línea con un correo
 * (uno por venta: la ida y, si hubo, el regreso), los próximos primero (el
 * que sale antes, arriba) y luego los pasados (el más reciente, arriba).
 * Un viaje próximo dice si el cliente puede modificarlo y hasta cuándo; uno
 * pasado, si tiene factura para descargar.
 */
final class MisCompras
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HorasSalida $horas,
        private readonly AjustesPagina $ajustes,
        private readonly FlujoBoleto $flujo,
        private readonly ClockInterface $reloj,
    ) {}

    /** Si hay alguna compra completada con ese correo (para enviarle el enlace de acceso). */
    public function tieneCompras(string $correo): bool
    {
        return $this->em->getRepository(PagoWeb::class)->count(["correo" => LlaveCliente::normalizar($correo), "estado" => EstadoPagoWeb::COMPLETADO]) > 0;
    }

    /**
     * @return array{correo: string, futuros: list<array<string, mixed>>, pasados: list<array<string, mixed>>, politica: array<string, mixed>}
     */
    public function de(string $correo): array
    {
        $ahora = $this->reloj->now();
        $politica = $this->ajustes->politicaModificacion();
        $futuros = [];
        $pasados = [];
        foreach ($this->pagos($correo) as $pago) {
            $ventas = array_values(array_filter([$pago->getBoletoVenta(), $pago->getBoletoVentaRegreso()]));
            foreach ($ventas as $i => $venta) {
                $viaje = $this->viaje($pago, $venta, count($ventas) > 1 ? ($i === 0 ? "ida" : "regreso") : null, $politica, $ahora);
                if ($viaje === null) {
                    continue;
                }
                if ($viaje["futuro"]) {
                    $futuros[] = $viaje;
                } else {
                    $pasados[] = $viaje;
                }
            }
        }
        // Próximos: el que sale antes primero. Pasados: el más reciente primero.
        usort($futuros, static fn(array $a, array $b) => [$a["fecha"], $a["id"]] <=> [$b["fecha"], $b["id"]]);
        usort($pasados, static fn(array $a, array $b) => [$b["fecha"], $b["id"]] <=> [$a["fecha"], $a["id"]]);

        return [
            "correo" => LlaveCliente::normalizar($correo),
            "futuros" => $futuros,
            "pasados" => $pasados,
            "politica" => [
                "modificacionesPorAsiento" => $politica->modificacionesPorAsiento,
                "minutosAntes" => $politica->minutosAntes,
                "penalizacion" => DatosBoleto::importe($politica->penalizacion->unitaria()),
                "penalizacionPorAsiento" => $politica->penalizacion->porAsiento,
            ],
        ];
    }

    /**
     * La venta, si es de una compra en línea de ese correo.
     *
     * @return array{venta: BoletoVenta, pago: PagoWeb}
     *
     * @throws VentaRechazada 404 si no existe o es de otro
     */
    public function venta(string $correo, int $ventaId): array
    {
        /** @var PagoWeb|null $pago */
        $pago = $this->em->createQueryBuilder()
            ->select("p")
            ->from(PagoWeb::class, "p")
            ->where("p.correo = :correo AND p.estado = :estado")
            ->andWhere("IDENTITY(p.boletoVenta) = :venta OR IDENTITY(p.boletoVentaRegreso) = :venta")
            ->setParameter("correo", LlaveCliente::normalizar($correo))
            ->setParameter("estado", EstadoPagoWeb::COMPLETADO)
            ->setParameter("venta", $ventaId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $venta = $pago?->getBoletoVenta()?->getId() === $ventaId ? $pago->getBoletoVenta() : $pago?->getBoletoVentaRegreso();
        if ($pago === null || $venta === null) {
            throw new VentaRechazada("No encontramos ese viaje entre sus compras.", "viaje_no_encontrado", 404);
        }

        return ["venta" => $venta, "pago" => $pago];
    }

    /**
     * Boletos vivos de la venta (ni anulados ni reasignados).
     *
     * @return list<BoletoAsiento>
     */
    public static function vivos(BoletoVenta $venta): array
    {
        $vivos = array_values(array_filter(
            $venta->getAsientos()->toArray(),
            static fn(BoletoAsiento $b) => !in_array($b->getEstado(), [EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::REASIGNADO], true),
        ));
        usort($vivos, static fn(BoletoAsiento $a, BoletoAsiento $b) => [$a->getSalida()->getFecha(), $a->getAsiento()->getNumero()] <=> [$b->getSalida()->getFecha(), $b->getAsiento()->getNumero()]);

        return $vivos;
    }

    /** @return list<PagoWeb> */
    private function pagos(string $correo): array
    {
        return $this->em->getRepository(PagoWeb::class)->findBy(
            ["correo" => LlaveCliente::normalizar($correo), "estado" => EstadoPagoWeb::COMPLETADO],
            ["id" => "DESC"],
            200,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function viaje(PagoWeb $pago, BoletoVenta $venta, ?string $sentido, PoliticaModificacion $politica, \DateTimeImmutable $ahora): ?array
    {
        $vivos = self::vivos($venta);
        $anulado = $vivos === [];
        $boletos = $anulado ? $venta->getAsientos()->toArray() : $vivos;
        if ($boletos === []) {
            return null;
        }
        $filas = [];
        $fecha = null;
        $motivoCerrada = null;
        foreach ($boletos as $b) {
            $subida = $this->horas->subida($b->getSalida(), (int) $b->getTrayecto()->getOrigen()->getId());
            $fecha = $fecha === null ? $subida["hora"] : min($fecha, $subida["hora"]);
            $motivo = $anulado ? "El viaje está anulado." : $politica->motivoCerrada($subida["hora"], $ahora);
            $motivo ??= $this->flujo->rechazo($b, TransicionBoleto::REASIGNAR)?->getMessage();
            $motivoCerrada ??= $motivo;
            $filas[] = [
                "id" => $b->getId(),
                "asiento" => $b->getAsiento()->getNumero(),
                "asientoId" => $b->getAsiento()->getId(),
                "clase" => $b->getAsiento()->getClase()->value,
                "precio" => DatosBoleto::importe($b->getPrecio()),
                "estado" => $b->getEstado()->value,
                "salida" => [
                    "id" => $b->getSalida()->getId(),
                    "salidaOrigen" => $subida["hora"]->format(DATE_ATOM),
                    "salidaPrecision" => $subida["precision"]->value,
                    "rutaOrigen" => $b->getSalida()->getRuta()?->getOrigen()?->getNombre(),
                    "llegada" => $this->horas->enParada($b->getSalida(), (int) $b->getTrayecto()->getDestino()->getId())?->format(DATE_ATOM),
                ],
                "trayecto" => [
                    "id" => $b->getTrayecto()->getId(),
                    "origen" => ["id" => $b->getTrayecto()->getOrigen()->getId(), "nombre" => $b->getTrayecto()->getOrigen()->getNombre()],
                    "destino" => ["id" => $b->getTrayecto()->getDestino()->getId(), "nombre" => $b->getTrayecto()->getDestino()->getNombre()],
                ],
                "modificaciones" => $b->getModificaciones(),
                "modificable" => $motivo === null && $politica->restantes($b->getModificaciones()) > 0,
                "motivo" => $motivo ?? ($politica->restantes($b->getModificaciones()) === 0 ? "Ya cambió este asiento las veces permitidas." : null),
            ];
        }
        $primero = $boletos[0];
        $futuro = !$anulado && $fecha > $ahora;
        $factura = $venta->getFactura();
        $editable = $futuro && $motivoCerrada === null;

        return [
            "id" => $venta->getId(),
            "compra" => $pago->getId(),
            "sentido" => $sentido,
            "comprada" => $pago->getCreado()->format(DATE_ATOM),
            "fecha" => $fecha->format(DATE_ATOM),
            "futuro" => $futuro,
            "anulado" => $anulado,
            "empresa" => $primero->getSalida()->getEmpresa()?->getNombreCorto(),
            "origen" => $primero->getTrayecto()->getOrigen()->getNombre(),
            "destino" => $primero->getTrayecto()->getDestino()->getNombre(),
            "codigoBarras" => sprintf("%08d", $venta->getId()),
            "boletos" => $filas,
            "total" => DatosBoleto::importe($venta->getTotal()),
            "cargoModificacion" => $venta->getCargoModificacion()->isPositive() ? DatosBoleto::importe($venta->getCargoModificacion()) : null,
            "estadoFacturacion" => $venta->getEstadoFacturacion()->value,
            "factura" => $factura === null ? null : [
                "serie" => $factura->getSerie(),
                "numero" => $factura->numeroDte(),
                "uuid" => strtoupper((string) $factura->getUuid()?->toRfc4122()),
                "anulada" => $factura->isAnulada(),
                "urlPdf" => $factura->getUrlPdf(),
            ],
            // Pasados: la factura se descarga. Próximos: se modifican (si la política lo permite).
            "descargarFactura" => !$futuro && $factura !== null && !$factura->isAnulada(),
            "editable" => $editable,
            "limiteModificacion" => $futuro ? $politica->limite($fecha)->format(DATE_ATOM) : null,
            "motivo" => $futuro ? $motivoCerrada : null,
        ];
    }
}
