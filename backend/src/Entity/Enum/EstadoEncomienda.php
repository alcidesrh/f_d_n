<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Estado de una `Encomienda` (ADR-035):
 * `recibida → embarcada → transito → desembarcada → entregada`, con transbordo
 * (desembarcada → embarcada) y `anulada` desde la bodega de su origen (o
 * embarcada, si va con un boleto que se anula).
 */
enum EstadoEncomienda: string
{
    /** En bodega en la estación donde se recibió. */
    case RECIBIDA = "recibida";
    /** Asignada a una salida que todavía no inicia. */
    case EMBARCADA = "embarcada";
    /** En una salida ya iniciada. */
    case TRANSITO = "transito";
    /** En bodega en una parada (su destino o un transbordo). */
    case DESEMBARCADA = "desembarcada";
    /** La retiró el destinatario (u otro en su nombre). */
    case ENTREGADA = "entregada";
    case ANULADA = "anulada";

    /** @return list<self> */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::RECIBIDA => [self::EMBARCADA, self::TRANSITO, self::ANULADA],
            // Se baja antes de salir (o en una parada) y queda en bodega ahí.
            // Anulada: la de un pasajero cuyo boleto se anula antes de salir.
            self::EMBARCADA => [self::TRANSITO, self::DESEMBARCADA, self::ANULADA],
            self::TRANSITO => [self::DESEMBARCADA],
            // Transbordo: se embarca en otra salida; en su destino se entrega.
            self::DESEMBARCADA => [self::EMBARCADA, self::TRANSITO, self::ENTREGADA, self::ANULADA],
            self::ENTREGADA, self::ANULADA => [],
        };
    }

    public function puedeTransicionarA(self $nuevo): bool
    {
        return in_array($nuevo, $this->transicionesPermitidas(), true);
    }

    /** Está guardada en la bodega de una estación (`Encomienda.ubicacion`). */
    public function enBodega(): bool
    {
        return $this === self::RECIBIDA || $this === self::DESEMBARCADA;
    }

    /** Va en un bus (`Encomienda.salida`). */
    public function aBordo(): bool
    {
        return $this === self::EMBARCADA || $this === self::TRANSITO;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::RECIBIDA => "Recibida",
            self::EMBARCADA => "Embarcada",
            self::TRANSITO => "En tránsito",
            self::DESEMBARCADA => "Desembarcada",
            self::ENTREGADA => "Entregada",
            self::ANULADA => "Anulada",
        };
    }
}
