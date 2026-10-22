<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Acceso\Capacidad;
use App\Entity\Enum\Dispensa;
use App\Entity\SolicitudAutorizacion;

/**
 * Lo que necesitaría una operación bloqueada para hacerse con una
 * autorización (ADR-040): la capacidad, de qué regla dispensar y sobre qué
 * registros. Va en la respuesta del bloqueo (`autorizable`) para que el
 * cliente ofrezca pedirla, y vuelve tal cual en la solicitud.
 */
final class Autorizable
{
    /**
     * @param list<int> $ids
     */
    public function __construct(
        public readonly Capacidad $capacidad,
        public readonly Dispensa $dispensa,
        public readonly string $sujeto,
        public readonly array $ids,
        public readonly int $cantidad,
    ) {}

    /** Sobre boletos: cubre esos boletos (uno por unidad). @param list<int> $boletos */
    public static function boletos(Capacidad $capacidad, Dispensa $dispensa, array $boletos): self
    {
        $ids = array_values(array_unique(array_map("intval", $boletos)));
        sort($ids);

        return new self($capacidad, $dispensa, SolicitudAutorizacion::SUJETO_BOLETOS, $ids, count($ids));
    }

    /** Sobre una salida: `$cantidad` asientos (cortesías). */
    public static function salida(Capacidad $capacidad, Dispensa $dispensa, int $salidaId, int $cantidad): self
    {
        return new self($capacidad, $dispensa, SolicitudAutorizacion::SUJETO_SALIDA, [$salidaId], max(1, $cantidad));
    }

    /**
     * Lo que manda el cliente al pedirla: `{ capacidad, dispensa, sujeto, ids, cantidad? }`.
     *
     * @param array<string, mixed> $datos
     *
     * @throws AutorizacionRechazada
     */
    public static function desdeArray(array $datos): self
    {
        $capacidad = Capacidad::tryFrom((string) ($datos["capacidad"] ?? ""))
            ?? throw new AutorizacionRechazada("Esa operación no existe.", "capacidad_invalida");
        $dispensa = Dispensa::tryFrom((string) ($datos["dispensa"] ?? ""))
            ?? throw new AutorizacionRechazada("Indique de qué regla pide la autorización.", "dispensa_invalida");
        $ids = array_values(array_filter(array_map("intval", (array) ($datos["ids"] ?? [])), static fn(int $id) => $id > 0));
        if ($ids === []) {
            throw new AutorizacionRechazada("Indique sobre qué registros pide la autorización.", "sin_registros");
        }

        return match ((string) ($datos["sujeto"] ?? "")) {
            SolicitudAutorizacion::SUJETO_BOLETOS => self::boletos($capacidad, $dispensa, $ids),
            SolicitudAutorizacion::SUJETO_SALIDA => self::salida($capacidad, $dispensa, $ids[0], (int) ($datos["cantidad"] ?? 1)),
            default => throw new AutorizacionRechazada("Indique sobre qué registros pide la autorización.", "sujeto_invalido"),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            "capacidad" => $this->capacidad->value,
            "operacion" => ReglasAutorizacion::operacion($this->capacidad),
            "dispensa" => $this->dispensa->value,
            "dispensaNombre" => $this->dispensa->etiqueta(),
            "sujeto" => $this->sujeto,
            "ids" => $this->ids,
            "cantidad" => $this->cantidad,
        ];
    }
}
