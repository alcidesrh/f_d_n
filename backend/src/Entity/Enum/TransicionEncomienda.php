<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Las transiciones con nombre del flujo de una `Encomienda` (ADR-035,
 * ADR-038): la operación que cambia su estado. De aquí salen la máquina de
 * estados de `symfony/workflow` (`config/packages/workflow.php`) y lo que
 * valida `Encomienda::setEstado()`; las reglas de cada una (guards) están en
 * `App\Encomienda\ReglasEncomienda::rechazo()`.
 */
enum TransicionEncomienda: string
{
    /** Sube de la bodega a una salida que todavía no inicia. */
    case EMBARCAR = "embarcar";
    /** Sube a una salida ya iniciada (en una parada intermedia): queda en tránsito. */
    case EMBARCAR_EN_RUTA = "embarcar_en_ruta";
    /** Embarcada e inició su salida (abordaje). */
    case PARTIR = "partir";
    /** Se baja en una parada de su salida (su destino o un transbordo) y queda en esa bodega. */
    case DESEMBARCAR = "desembarcar";
    /** La retira el destinatario (u otro en su nombre) en su destino. */
    case ENTREGAR = "entregar";
    /** No ha viajado: en la bodega de su origen. */
    case ANULAR = "anular";
    /** Va con un boleto: se anula al anular todos los boletos de su venta (ADR-028). */
    case ANULAR_CON_BOLETO = "anular_con_boleto";

    /** Nombre del workflow (`workflow:dump encomienda`). */
    public const WORKFLOW = "encomienda";

    /** @return list<EstadoEncomienda> */
    public function desde(): array
    {
        return match ($this) {
            self::EMBARCAR, self::EMBARCAR_EN_RUTA, self::ANULAR => [EstadoEncomienda::RECIBIDA, EstadoEncomienda::DESEMBARCADA],
            self::PARTIR => [EstadoEncomienda::EMBARCADA],
            self::DESEMBARCAR => [EstadoEncomienda::EMBARCADA, EstadoEncomienda::TRANSITO],
            self::ENTREGAR => [EstadoEncomienda::DESEMBARCADA],
            self::ANULAR_CON_BOLETO => [EstadoEncomienda::RECIBIDA, EstadoEncomienda::EMBARCADA, EstadoEncomienda::DESEMBARCADA],
        };
    }

    public function hacia(): EstadoEncomienda
    {
        return match ($this) {
            self::EMBARCAR => EstadoEncomienda::EMBARCADA,
            self::EMBARCAR_EN_RUTA, self::PARTIR => EstadoEncomienda::TRANSITO,
            self::DESEMBARCAR => EstadoEncomienda::DESEMBARCADA,
            self::ENTREGAR => EstadoEncomienda::ENTREGADA,
            self::ANULAR, self::ANULAR_CON_BOLETO => EstadoEncomienda::ANULADA,
        };
    }

    /** Embarcar según la salida: con ella iniciada, la encomienda pasa directo a tránsito. */
    public static function alEmbarcar(EstadoSalida $salida): self
    {
        return $salida === EstadoSalida::INICIADA ? self::EMBARCAR_EN_RUTA : self::EMBARCAR;
    }

    /** Lo que exige del estado de la encomienda, para el mensaje cuando no lo cumple. */
    public function requisito(): string
    {
        return match ($this) {
            self::EMBARCAR, self::EMBARCAR_EN_RUTA => "solo se embarca una encomienda en bodega (recibida o desembarcada)",
            self::PARTIR => "solo parte una encomienda embarcada",
            self::DESEMBARCAR => "solo se desembarca una encomienda embarcada o en tránsito",
            self::ENTREGAR => "solo se entrega una encomienda desembarcada en su destino o en tránsito hacia él",
            self::ANULAR => "solo se anula una encomienda que no ha viajado (en la bodega de su origen)",
            self::ANULAR_CON_BOLETO => "ya viajó: no se anula con su venta",
        };
    }
}
