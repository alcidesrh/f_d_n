<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
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
     * @return array<string, mixed>
     */
    public static function de(BoletoVenta $venta, ?\DateTimeImmutable $salidaOrigen = null): array
    {
        $boletos = $venta->getAsientos()->toArray();
        usort($boletos, static fn(BoletoAsiento $a, BoletoAsiento $b) => $a->getAsiento()->getNumero() <=> $b->getAsiento()->getNumero());
        $primero = $boletos[0] ?? null;
        $recorrido = $primero?->getRecorrido();
        $trayecto = $primero?->getTrayecto();
        $empresa = $recorrido?->getEmpresa();
        $factura = $venta->getFactura();
        $cliente = $venta->getCliente();

        return [
            "id" => $venta->getId(),
            "token" => $venta->getTokenPublico()?->toRfc4122(),
            "canal" => $venta->getCanal()->value,
            "estado" => $venta->getEstado()->value,
            "estadoFacturacion" => $venta->getEstadoFacturacion()->value,
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
            ],
            "cliente" => $cliente === null ? null : [
                "nombre" => $cliente->getNombreCompleto(),
                "nit" => $cliente->getNit() ?: "CF",
                "email" => $cliente->getEmail(),
            ],
            "recorrido" => $recorrido === null ? null : [
                "id" => $recorrido->getId(),
                // Salida del inicio de la ruta y hora estimada donde sube el pasajero.
                "salida" => $recorrido->getFecha()->format(DATE_ATOM),
                "salidaOrigen" => ($salidaOrigen ?? \DateTimeImmutable::createFromMutable($recorrido->getFecha()))->format(DATE_ATOM),
                "bus" => $recorrido->getBus()?->getCodigo(),
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
            "total" => self::importe($venta->getTotal()),
            "tipoPago" => $venta->getTipoPago()?->getNombre(),
        ];
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
