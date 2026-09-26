<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Elementos no vendibles del croquis de un bus (`BusSenal`). En el legado son
 * las filas de `bus_senal_tipo`; se identifican por nombre al migrar.
 */
enum TipoBusSenal: string
{
    case CHOFER = "chofer";
    case PUERTA = "puerta";

    /** Tipo equivalente a un nombre de `bus_senal_tipo` del legado, o null si no se reconoce. */
    public static function desdeNombreLegado(?string $nombre): ?self
    {
        $nombre = mb_strtolower(trim((string) $nombre));

        return match (true) {
            $nombre === "" => null,
            str_contains($nombre, "puerta") => self::PUERTA,
            str_contains($nombre, "chofer"),
            str_contains($nombre, "piloto"),
            str_contains($nombre, "conductor"),
            str_contains($nombre, "motorista"),
                => self::CHOFER,
            default => null,
        };
    }
}
