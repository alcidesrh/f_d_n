<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\CanalVenta;

/**
 * Un boleto vendido aquí, con lo que el legado necesita para escribirlo
 * (ADR-042). Los ids de estación son los del legado (los enclaves los
 * conservan); los de cliente y usuario, los de aquí: `EscritorLegado` los
 * resuelve.
 */
final readonly class BoletoParaLegado
{
    public function __construct(
        /** Clave en `identificador_web` del legado: así se reconoce lo que escribió FDN. */
        public string $clave,
        public int $salidaLegado,
        public int $asiento,
        public int $origen,
        public int $destino,
        public int $pasajero,
        public int $comprador,
        public int $estado,
        public int $precio,
        public int $tipoDocumento,
        public ?int $tipoPago,
        public ?int $moneda,
        public ?int $estacion,
        public ?int $usuario,
        public ?string $observacion,
        public ?string $reasignadoDe,
    ) {}

    /**
     * @param ?string $reasignadoDe `legacyId` del boleto del que viene (reasignación)
     */
    public static function de(BoletoAsiento $b, string $clave, ?string $reasignadoDe): ?self
    {
        $venta = $b->getBoletoVenta();
        $salida = $b->getSalida();
        $trayecto = $b->getTrayecto();
        if ($salida?->getLegacyId() === null || $venta === null || $trayecto === null || $b->getAsiento() === null || $b->getCliente() === null) {
            return null;
        }

        return new self(
            clave: $clave,
            salidaLegado: (int) $salida->getLegacyId(),
            asiento: (int) $b->getAsiento()->getNumero(),
            origen: (int) $trayecto->getOrigen()?->getId(),
            destino: (int) $trayecto->getDestino()?->getId(),
            pasajero: (int) $b->getCliente()->getId(),
            comprador: (int) ($venta->getCliente()?->getId() ?? $b->getCliente()->getId()),
            estado: Traduccion::estadoBoleto($b->getEstado()),
            precio: (int) ($b->getPrecio()?->getAmount() ?? 0),
            tipoDocumento: Traduccion::tipoDocumentoBoleto($venta->getCanal(), $venta->isCortesia()),
            tipoPago: $venta->getTipoPago()?->getId(),
            moneda: $venta->getMoneda()?->getId(),
            estacion: $venta->getCanal() === CanalVenta::AGENCIA ? $venta->getAgencia()?->getId() : $venta->getEstacion()?->getId(),
            usuario: $venta->getUsuario()?->getId(),
            observacion: $b->getObservacion(),
            reasignadoDe: $reasignadoDe,
        );
    }
}
