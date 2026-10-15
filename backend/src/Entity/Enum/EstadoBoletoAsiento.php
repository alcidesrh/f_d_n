<?php

namespace App\Entity\Enum;

enum EstadoBoletoAsiento: string
{
    case EMITIDO = "emitido";
    case CHEQUEADO = "chequeado";
    case TRANSITO = "transito";
    case FINALIZADO = "finalizado";
    case ANULADO = "anulado";
    case REASIGNADO = "reasignado";
    /** El pasajero no se presentó a abordar: el asiento queda libre, la venta y su factura siguen (ADR-032). Se revierte a emitido si el asiento sigue libre (ADR-033). */
    case CANCELADO = "cancelado";
    /** Compra web pospuesta: el asiento queda libre, la venta y su factura siguen; se reanuda reasignándolo (o se anula). */
    case POSPUESTO = "pospuesto";

    /**
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::EMITIDO => [
                self::CHEQUEADO,
                // Chequeado con la salida ya iniciada (sube en una parada intermedia).
                self::TRANSITO,
                self::ANULADO,
                self::REASIGNADO,
                self::CANCELADO,
                self::POSPUESTO,
            ],
            // Chequeado que al final no viajó: se cancela desde el listado de boletos.
            self::CHEQUEADO => [self::TRANSITO, self::CANCELADO],
            self::TRANSITO => [self::FINALIZADO],
            // Revertir la cancelación (el pasajero sí viaja), si el asiento sigue libre.
            self::CANCELADO => [self::EMITIDO],
            // Se reanuda reasignándolo a otra salida, o se anula.
            self::POSPUESTO => [self::REASIGNADO, self::ANULADO],
            self::FINALIZADO, self::ANULADO, self::REASIGNADO => [],
        };
    }

    public function puedeTransicionarA(self $siguiente): bool
    {
        return in_array($siguiente, $this->transicionesPermitidas(), true);
    }

    /**
     * Estados que no ocupan el asiento: se puede volver a vender en ese tramo.
     * Debe coincidir con la condición de `ex_boleto_asiento_tramo`.
     *
     * @return list<string>
     */
    public static function liberanAsiento(): array
    {
        return [self::ANULADO->value, self::REASIGNADO->value, self::CANCELADO->value, self::POSPUESTO->value];
    }
}
