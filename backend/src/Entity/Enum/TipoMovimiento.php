<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Qué cambió en las existencias de un almacén (ADR-071). Cada movimiento es
 * una línea del libro de ese almacén: la existencia es la suma de sus líneas.
 *
 * - `entrada`: lo que llega comprado (con su costo y su factura).
 * - `salida`: lo que se gasta o se instala (puede citar la afectación).
 * - `envio` / `recepcion`: un traslado en dos pasos; sale del almacén que
 *   envía al despacharlo y entra al que lo recibe cuando llega. Entre los
 *   dos está «en camino».
 * - `ajuste`: la diferencia de un conteo, con su motivo.
 */
enum TipoMovimiento: string
{
    case ENTRADA = "entrada";
    case SALIDA = "salida";
    case ENVIO = "envio";
    case RECEPCION = "recepcion";
    case AJUSTE = "ajuste";

    public function etiqueta(): string
    {
        return match ($this) {
            self::ENTRADA => "Entrada",
            self::SALIDA => "Salida",
            self::ENVIO => "Envío",
            self::RECEPCION => "Recepción",
            self::AJUSTE => "Ajuste",
        };
    }
}
