<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Encomienda;
use App\Entity\Enum\EstadoEncomienda;
use App\Encomienda\DatosEncomienda;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\PrecisionHora;
use Money\Money;

/**
 * Datos del comprobante de una venta (ticket de taquilla, PDF de la web y
 * respuesta de la API): factura + boletos, con importes ya formateados. Lo
 * mismo que imprime el legado en el ticket de 80 mm. Con la hora en la
 * parada de subida, usar el servicio `Comprobantes`.
 */
final class DatosBoleto
{
    /**
     * Los boletos que lleva el comprobante, por número de asiento: los
     * pedidos (`$soloIds`) o, si no, los vivos (ni anulados ni reasignados);
     * si ya no queda ninguno viva, todos (para poder consultar la venta).
     *
     * @param list<int>|null $soloIds
     *
     * @return list<BoletoAsiento>
     */
    public static function boletos(BoletoVenta $venta, ?array $soloIds = null): array
    {
        $todos = $venta->getAsientos()->toArray();
        $boletos = $soloIds !== null
            ? array_values(array_filter($todos, static fn(BoletoAsiento $b) => in_array($b->getId(), $soloIds, true)))
            : array_values(array_filter($todos, static fn(BoletoAsiento $b) => !in_array($b->getEstado(), [EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::REASIGNADO], true)));
        if ($boletos === [] && $soloIds === null) {
            $boletos = $todos;
        }
        usort($boletos, static fn(BoletoAsiento $a, BoletoAsiento $b) => $a->getAsiento()->getNumero() <=> $b->getAsiento()->getNumero());

        return $boletos;
    }

    /**
     * @param list<int>|null $soloIds boletos que se imprimen (por defecto, los vivos de la venta)
     *
     * @return array<string, mixed>
     */
    /**
     * @param array{hora: \DateTimeImmutable, precision: PrecisionHora}|null $subida hora donde sube el pasajero (`HorasSalida::subida`)
     */
    public static function de(BoletoVenta $venta, ?array $subida = null, ?array $soloIds = null): array
    {
        $boletos = self::boletos($venta, $soloIds);
        $todos = $venta->getAsientos()->count();
        $primero = $boletos[0] ?? null;
        $salida = $primero?->getSalida();
        $trayecto = $primero?->getTrayecto();
        $empresa = $salida?->getEmpresa();
        $factura = $venta->getFactura();
        $cliente = $venta->getCliente();
        /** @var list<Encomienda> $encomiendas */
        $encomiendas = $soloIds !== null ? [] : array_values(array_filter(
            $venta->getEncomiendas()->toArray(),
            static fn(Encomienda $e) => $e->getEstado() !== EstadoEncomienda::ANULADA,
        ));

        return [
            "id" => $venta->getId(),
            "token" => $venta->getTokenPublico()?->toRfc4122(),
            "canal" => $venta->getCanal()->value,
            "estado" => $venta->getEstado()->value,
            "estadoFacturacion" => $venta->getEstadoFacturacion()->value,
            "numeroAcceso" => $venta->getNumeroAcceso(),
            "cortesia" => $venta->isCortesia(),
            "creada" => $venta->getCreada()?->format(DATE_ATOM),
            "codigoBarras" => sprintf("%08d", $venta->getId()),
            "empresa" => $empresa === null ? null : [
                "nombre" => $empresa->getNombre(),
                "nit" => $empresa->getNit(),
                "direccion" => $empresa->getDireccion(),
                "telefono" => $empresa->getTelefono(),
            ],
            "estacion" => $venta->getEstacion() === null ? null : [
                "nombre" => $venta->getEstacion()->getNombre(),
                "direccion" => $venta->getEstacion()->getDireccion(),
            ],
            "agencia" => $venta->getAgencia()?->getNombre(),
            "vendedor" => $venta->getUsuario()?->getUsername(),
            "factura" => $factura === null ? null : [
                "numero" => $factura->getDte(),
                "serie" => $factura->getSerie(),
                "uuid" => strtoupper((string) $factura->getUuid()?->toRfc4122()),
                "fechaCertificacion" => ($factura->getFechaCertificacion() ?? $factura->getFecha())?->format(DATE_ATOM),
                "certificador" => $factura->getCertificadorNombre(),
                "certificadorNit" => $factura->getCertificadorNit(),
                "receptorNit" => $factura->getReceptopNit(),
                "receptorNombre" => $factura->getReceptorNombre(),
                "urlPdf" => $factura->getUrlPdf(),
            ],
            "cliente" => $cliente === null ? null : [
                "nombre" => $cliente->getNombreCompleto(),
                "nit" => $cliente->getNit() ?: "CF",
                "email" => $cliente->getEmail(),
            ],
            "salida" => $salida === null ? null : [
                "id" => $salida->getId(),
                // Salida del inicio de la ruta y hora donde sube el pasajero, con su
                // precisión (`estimada` se imprime "aprox."; `desconocida` es la del
                // origen del bus y se imprime como tal, ADR-030).
                "salida" => $salida->getFecha()->format(DATE_ATOM),
                "salidaOrigen" => ($subida["hora"] ?? \DateTimeImmutable::createFromMutable($salida->getFecha()))->format(DATE_ATOM),
                "salidaPrecision" => ($subida["precision"] ?? PrecisionHora::EXACTA)->value,
                "rutaOrigen" => $salida->getRuta()?->getOrigen()?->getNombre(),
                "bus" => $salida->getBus()?->getCodigo(),
            ],
            "origen" => $trayecto === null ? null : [
                "nombre" => $trayecto->getOrigen()->getNombre(),
                "direccion" => $trayecto->getOrigen()->getDireccion(),
            ],
            "destino" => $trayecto === null ? null : [
                "nombre" => $trayecto->getDestino()->getNombre(),
                "direccion" => $trayecto->getDestino()->getDireccion(),
            ],
            "boletos" => array_map(static fn(BoletoAsiento $b) => [
                "id" => $b->getId(),
                "asiento" => $b->getAsiento()->getNumero(),
                "clase" => $b->getAsiento()->getClase()->value,
                "pasajero" => $b->getCliente()?->getNombreCompleto(),
                "precio" => self::importe($b->getPrecio()),
                "observacion" => $b->getObservacion(),
                "estado" => $b->getEstado()->value,
            ], $boletos),
            // Encomiendas que lleva el pasajero (ADR-035): van en el ticket de la venta y cada bulto con su guía.
            "encomiendas" => array_map(static fn(Encomienda $e) => DatosEncomienda::de($e), $encomiendas),
            "total" => self::importe(count($boletos) === $todos ? $venta->getTotal() : self::suma($boletos, $venta->getTotal(), $encomiendas)),
            "tipoPago" => $venta->getTipoPago()?->getNombre(),
        ];
    }

    /**
     * Lo que suman los boletos que lleva el comprobante cuando no son todos
     * los de la venta.
     *
     * @param list<BoletoAsiento> $boletos
     * @param list<Encomienda>     $encomiendas
     */
    private static function suma(array $boletos, Money $venta, array $encomiendas = []): Money
    {
        $total = array_reduce(
            $boletos,
            static fn(Money $total, BoletoAsiento $b) => $b->getPrecio() === null ? $total : $total->add($b->getPrecio()),
            new Money(0, $venta->getCurrency()),
        );

        return array_reduce($encomiendas, static fn(Money $t, Encomienda $e) => $t->add($e->getTotal()), $total);
    }

    /** @return array{centavos: int, moneda: string, texto: string}|null */
    public static function importe(?Money $monto): ?array
    {
        if ($monto === null) {
            return null;
        }
        $centavos = (int) $monto->getAmount();
        $moneda = $monto->getCurrency()->getCode();

        return [
            "centavos" => $centavos,
            "moneda" => $moneda,
            "texto" => sprintf("%s %s", $moneda === "GTQ" ? "Q" : $moneda, number_format($centavos / 100, 2)),
        ];
    }
}
