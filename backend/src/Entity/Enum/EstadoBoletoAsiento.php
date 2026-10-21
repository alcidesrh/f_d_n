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
     * Los estados a los que llega alguna transición del flujo
     * (`TransicionBoleto`, ADR-037).
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        $hacia = [];
        foreach (TransicionBoleto::cases() as $t) {
            if (in_array($this, $t->desde(), true) && !in_array($t->hacia(), $hacia, true)) {
                $hacia[] = $t->hacia();
            }
        }

        return $hacia;
    }

    public function puedeTransicionarA(self $siguiente): bool
    {
        return in_array($siguiente, $this->transicionesPermitidas(), true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::EMITIDO => "Emitido",
            self::CHEQUEADO => "Chequeado",
            self::TRANSITO => "En tránsito",
            self::FINALIZADO => "Finalizado",
            self::ANULADO => "Anulado",
            self::REASIGNADO => "Reasignado",
            self::CANCELADO => "Cancelado",
            self::POSPUESTO => "Pospuesto",
        };
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
