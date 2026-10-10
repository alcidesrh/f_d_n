<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Entity\BoletoVenta;
use App\Entity\Empresa;
use App\Entity\Establecimiento;
use App\Entity\Factura;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $em,
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
            ->setUrlPdf($dte->urlPdf)
            ->setXml($dte->xml);

        $venta->certificar($factura);

        return $factura;
    }

    /**
     * Anula la factura certificada de la venta en el certificador y la marca
     * anulada (sin hacer flush). Si ya estaba anulada no vuelve a llamarlo:
     * sirve para reintentar una anulación que se quedó a medias.
     *
     * @throws CertificacionFallida si el certificador no la anuló
     */
    public function anular(BoletoVenta $venta, string $motivo): ?Factura
    {
        $factura = $venta->getFactura();
        if ($factura === null || $factura->isAnulada()) {
            return $factura;
        }
        $ahora = $this->reloj->now();
        $this->certificador->anular(new SolicitudAnulacion(
            uuid: (string) $factura->getUuid()?->toRfc4122(),
            emisorNit: (string) $factura->getEmisorNit(),
            receptorNit: (string) $factura->getReceptopNit(),
            fechaEmision: \DateTimeImmutable::createFromMutable($factura->getFecha()),
            fechaAnulacion: $ahora,
            motivo: $motivo,
        ));
        $factura->marcarAnulada($motivo, $ahora);

        return $factura;
    }

    public function solicitud(BoletoVenta $venta): SolicitudDte
    {
        $boletos = $venta->getAsientos()->toArray();
        if ($boletos === []) {
            throw new \LogicException("Una venta sin boletos no se factura.");
        }
        $empresa = $boletos[0]->getSalida()->getEmpresa();
        if ($empresa === null || !$empresa->getNit()) {
            throw new CertificacionFallida(
                "La empresa del salida no tiene NIT configurado: no se puede facturar.",
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
                    $b->getSalida()->getFecha()->format("d/m/Y H:i"),
                ),
                1,
                $precio,
                $precio,
                $b->getAsiento()->getNumero(),
                $b->getCliente()?->getNombreCompleto(),
            );
        }
        $trayecto = $boletos[0]->getTrayecto();

        $cliente = $venta->getCliente();
        $nit = self::normalizarNit($cliente?->getNit());

        // En contingencia el documento se emitió al vender (con su número de acceso).
        $contingencia = $venta->getNumeroAcceso() !== null && $venta->getCreada() !== null;

        return new SolicitudDte(
            referenciaInterna: sprintf("FDN-VENTA-%d", $venta->getId()),
            fechaEmision: $contingencia
                ? \DateTimeImmutable::createFromMutable($venta->getCreada())
                : $this->reloj->now(),
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
            establecimientoCodigo: $this->establecimiento($empresa, $venta)->getCodigo(),
            afiliacionIva: $empresa->getAfiliacionIva(),
            frases: $empresa->frases(),
            numeroAcceso: $venta->getNumeroAcceso(),
            ruta: sprintf("%s - %s", $trayecto->getOrigen()->getNombre(), $trayecto->getDestino()->getNombre()),
        );
    }

    /** El de la estación que vende o, si no tiene, el por defecto de la empresa. */
    private function establecimiento(Empresa $empresa, BoletoVenta $venta): Establecimiento
    {
        $repo = $this->em->getRepository(Establecimiento::class);
        $propio = $venta->getEstacion() !== null
            ? $repo->findOneBy(["empresa" => $empresa, "estacion" => $venta->getEstacion()])
            : null;

        return $propio
            ?? $repo->findOneBy(["empresa" => $empresa, "estacion" => null])
            ?? throw new CertificacionFallida(
                sprintf("La empresa %s no tiene establecimiento FEL configurado: no se puede facturar.", $empresa->getNombre()),
                false,
                "sin_establecimiento",
            );
    }

    /** Número de acceso de contingencia de la SAT: 9 dígitos al azar. */
    public static function nuevoNumeroAcceso(): int
    {
        return random_int(100000000, 999999999);
    }

    /** La SAT no admite facturar a consumidor final (CF) desde este monto. */
    public const TOPE_CONSUMIDOR_FINAL = 250000;

    /** NIT sin guiones ni espacios, en mayúsculas; vacío = consumidor final (`CF`). */
    public static function normalizarNit(?string $nit): string
    {
        $nit = strtoupper(preg_replace('/[\s-]+/', '', (string) $nit) ?? "");

        return $nit === "" || $nit === "C/F" ? "CF" : $nit;
    }
}
