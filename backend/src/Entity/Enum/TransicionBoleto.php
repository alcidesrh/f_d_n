<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Las transiciones con nombre del flujo de un `BoletoAsiento` (ADR-037): la
 * operación del negocio que cambia su estado. De aquí salen la máquina de
 * estados de `symfony/workflow` (`config/packages/workflow.php`) y lo que
 * valida `BoletoAsiento::setEstado()`; las reglas de cada una (guards) están
 * en `App\Venta\Boleto\Flujo\ReglasTransicion`.
 */
enum TransicionBoleto: string
{
    /** Se presentó a abordar con la salida abordando. */
    case CHEQUEAR = "chequear";
    /** Se presentó con la salida ya iniciada (sube en una parada intermedia): queda en tránsito. */
    case CHEQUEAR_EN_RUTA = "chequear_en_ruta";
    /** Chequeado e inició la salida (o finalizó una migrada que no pasó por iniciar). */
    case PARTIR = "partir";
    /** En tránsito y finalizó la salida. */
    case LLEGAR = "llegar";
    /** No se presentó: NO en el chequeo, emitido en el origen al iniciar o sin chequear al finalizar (ADR-032). */
    case NO_PRESENTARSE = "no_presentarse";
    /** Cancelado desde el listado de boletos: el pasajero no viaja (ADR-033). */
    case CANCELAR = "cancelar";
    /** El cancelado sí viaja: vuelve a emitido si el asiento sigue libre (ADR-033). */
    case REVERTIR = "revertir";
    /** Anulación de la compra del asiento (ADR-028). */
    case ANULAR = "anular";
    /** Pasa a otro asiento o salida: el boleto nuevo queda con `reasignadoDe` (ADR-028). */
    case REASIGNAR = "reasignar";
    /** Compra web pospuesta desde el dashboard: libera el asiento, se reanuda reasignándolo. */
    case POSPONER = "posponer";

    /** Nombre del workflow (`workflow:dump boleto_asiento`). */
    public const WORKFLOW = "boleto_asiento";

    /** @return list<EstadoBoletoAsiento> */
    public function desde(): array
    {
        return match ($this) {
            self::CHEQUEAR, self::CHEQUEAR_EN_RUTA, self::NO_PRESENTARSE, self::POSPONER => [EstadoBoletoAsiento::EMITIDO],
            self::PARTIR => [EstadoBoletoAsiento::CHEQUEADO],
            self::LLEGAR => [EstadoBoletoAsiento::TRANSITO],
            self::CANCELAR => [EstadoBoletoAsiento::EMITIDO, EstadoBoletoAsiento::CHEQUEADO],
            self::REVERTIR => [EstadoBoletoAsiento::CANCELADO],
            self::ANULAR, self::REASIGNAR => [EstadoBoletoAsiento::EMITIDO, EstadoBoletoAsiento::POSPUESTO],
        };
    }

    public function hacia(): EstadoBoletoAsiento
    {
        return match ($this) {
            self::CHEQUEAR => EstadoBoletoAsiento::CHEQUEADO,
            self::CHEQUEAR_EN_RUTA, self::PARTIR => EstadoBoletoAsiento::TRANSITO,
            self::LLEGAR => EstadoBoletoAsiento::FINALIZADO,
            self::NO_PRESENTARSE, self::CANCELAR => EstadoBoletoAsiento::CANCELADO,
            self::REVERTIR => EstadoBoletoAsiento::EMITIDO,
            self::ANULAR => EstadoBoletoAsiento::ANULADO,
            self::REASIGNAR => EstadoBoletoAsiento::REASIGNADO,
            self::POSPONER => EstadoBoletoAsiento::POSPUESTO,
        };
    }

    /** Chequear según la salida: con ella iniciada, el boleto pasa directo a tránsito. */
    public static function alChequear(EstadoSalida $salida): self
    {
        return $salida === EstadoSalida::INICIADA ? self::CHEQUEAR_EN_RUTA : self::CHEQUEAR;
    }

    /** Lo que exige del estado del boleto, para el mensaje cuando no lo cumple. */
    public function requisito(): string
    {
        return match ($this) {
            self::CHEQUEAR, self::CHEQUEAR_EN_RUTA => "solo se chequea un boleto emitido",
            self::NO_PRESENTARSE => "solo un boleto emitido queda como no presentado",
            self::PARTIR => "solo parte un boleto chequeado",
            self::LLEGAR => "solo llega un boleto en tránsito",
            self::CANCELAR => "solo se cancela un boleto emitido o chequeado",
            self::REVERTIR => "solo se revierte un boleto cancelado",
            self::ANULAR, self::REASIGNAR => "solo se opera sobre boletos emitidos o pospuestos",
            self::POSPONER => "solo se pospone un boleto emitido",
        };
    }

    /** Código de error de la API cuando el estado del boleto no la admite. */
    public function codigoEstado(): string
    {
        return match ($this) {
            self::CANCELAR => "boleto_no_cancelable",
            self::REVERTIR => "boleto_no_cancelado",
            self::PARTIR, self::LLEGAR => "boleto_no_operable",
            default => "boleto_no_emitido",
        };
    }

    /**
     * La venta (anular, reasignar, posponer: `App\Venta`, `VentaRechazada`)
     * o el abordaje (el resto: `App\Salida`, `SalidaRechazada`).
     */
    public function deLaVenta(): bool
    {
        return in_array($this, [self::ANULAR, self::REASIGNAR, self::POSPONER], true);
    }
}
