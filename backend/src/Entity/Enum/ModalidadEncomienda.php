<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/** Cómo se cobra una `Encomienda` (ADR-035). */
enum ModalidadEncomienda: string
{
    /** Se paga y se factura al recibirla. */
    case PAGADA = "pagada";
    /** La paga quien la retira: se factura al entregarla. */
    case POR_COBRAR = "por_cobrar";
    /** Envío interno de la empresa: sin cobro ni factura (permiso `encomienda.interna`). */
    case INTERNA = "interna";
    /** La lleva un pasajero: se cobra y se factura con la venta de su boleto. */
    case CON_BOLETO = "con_boleto";

    public function etiqueta(): string
    {
        return match ($this) {
            self::PAGADA => "Pagada",
            self::POR_COBRAR => "Por cobrar",
            self::INTERNA => "Interna",
            self::CON_BOLETO => "Con boleto",
        };
    }

    /** Lleva factura propia de la encomienda (al recibir o al entregar). */
    public function facturaPropia(): bool
    {
        return $this === self::PAGADA || $this === self::POR_COBRAR;
    }
}
