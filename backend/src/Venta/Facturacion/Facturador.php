<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Entity\BoletoVenta;
use App\Entity\Factura;
use Money\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Arma el DTE de una venta, lo manda a certificar y deja la `Factura` en la
 * venta (sin hacer flush: lo decide quien llama, dentro de su transacción).
 */
final class Facturador
{
    public function __construct(
        private readonly CertificadorFel $certificador,
        private readonly ClockInterface $reloj,
    ) {}

    /**
     * @throws CertificacionFallida
     */
    public function certificar(BoletoVenta $venta): Factura
    {
        $solicitud = $this->solicitud($venta);
        $dte = $this->certificador->certificar($solicitud);

        $factura = (new Factura())
            ->setDte($dte->numero)
            ->setUuid(Uuid::fromString($dte->uuid))
            ->setSerie($dte->serie)
            ->setFecha(\DateTime::createFromImmutable($solicitud->fechaEmision))
            ->setFechaCertificacion($dte->fechaCertificacion)
            ->setEmisorNit($solicitud->emisorNit)
            ->setEmisorNombre($solicitud->emisorNombre)
            ->setEmisorNombreComercial($dte->emisorNombreComercial)
            ->setEstablecimientoCodigo($dte->establecimientoCodigo)
            ->setReceptopNit($solicitud->receptorNit)
            ->setReceptorNombre($solicitud->receptorNombre)
            ->setCertificadorNit($dte->certificadorNit)
            ->setCertificadorNombre($dte->certificadorNombre)
            ->setTotal($solicitud->total)
            ->setXml($dte->xml);

        $venta->certificar($factura);

        return $factura;
    }

    public function solicitud(BoletoVenta $venta): SolicitudDte
    {
        $boletos = $venta->getAsientos()->toArray();
        if ($boletos === []) {
            throw new \LogicException("Una venta sin boletos no se factura.");
        }
        $empresa = $boletos[0]->getRecorrido()->getEmpresa();
        if ($empresa === null || !$empresa->getNit()) {
            throw new CertificacionFallida(
                "La empresa del recorrido no tiene NIT configurado: no se puede facturar.",
                false,
                "emisor_sin_nit",
            );
        }

        $items = [];
        foreach ($boletos as $b) {
            $precio = $b->getPrecio() ?? new Money(0, $venta->getTotal()->getCurrency());
            $items[] = new ItemDte(
                sprintf(
                    "Boleto %s - %s, asiento %d, salida %s",
                    $b->getTrayecto()->getOrigen()->getNombre(),
                    $b->getTrayecto()->getDestino()->getNombre(),
                    $b->getAsiento()->getNumero(),
                    $b->getRecorrido()->getFecha()->format("d/m/Y H:i"),
                ),
                1,
                $precio,
                $precio,
            );
        }

        $cliente = $venta->getCliente();
        $nit = self::normalizarNit($cliente?->getNit());

        return new SolicitudDte(
            referenciaInterna: sprintf("FDN-VENTA-%d", $venta->getId()),
            fechaEmision: $this->reloj->now(),
            emisorNit: $empresa->getNit(),
            emisorNombre: $empresa->getNombre(),
            emisorDireccion: $empresa->getDireccion(),
            establecimiento: $venta->getEstacion()?->getNombre()
                ?? ($venta->getAgencia()?->getNombre() ?? "Venta en línea"),
            receptorNit: $nit,
            receptorNombre: $nit === "CF"
                ? ($cliente?->getNombreCompleto() ?: "Consumidor final")
                : ($cliente?->getNombreCompleto() ?: "Sin nombre"),
            receptorCorreo: $venta->isEnviarCorreo() ? $cliente?->getEmail() : null,
            items: $items,
            total: $venta->getTotal(),
        );
    }

    /** NIT sin guiones ni espacios, en mayúsculas; vacío = consumidor final (`CF`). */
    public static function normalizarNit(?string $nit): string
    {
        $nit = strtoupper(preg_replace('/[\s-]+/', '', (string) $nit) ?? "");

        return $nit === "" || $nit === "C/F" ? "CF" : $nit;
    }
}
