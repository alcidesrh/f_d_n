<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Bus;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Entity\Trayecto;

/** Cómo se ve una salida (y sus partes) en la API de gestión de salidas. */
final class VistaSalida
{
    /** `Origen → Destino` (o el nombre del trayecto si lo tiene). */
    public static function ruta(Trayecto $t): string
    {
        return $t->getNombre() ?: sprintf("%s → %s", $t->getOrigen()?->getNombre() ?? "?", $t->getDestino()?->getNombre() ?? "?");
    }

    /** @return array{id: int, origen: ?string, destino: ?string, ruta: string, duracionMinutos: ?int} */
    public static function trayecto(Trayecto $t): array
    {
        return [
            "id" => (int) $t->getId(),
            "origen" => $t->getOrigen()?->getNombre(),
            "destino" => $t->getDestino()?->getNombre(),
            "ruta" => self::ruta($t),
            "duracionMinutos" => $t->getDuracionEstimadaMinutos(),
        ];
    }

    /** @return array{id: int, codigo: string, matricula: ?string, empresaId: ?int} */
    public static function bus(Bus $b): array
    {
        return [
            "id" => (int) $b->getId(),
            "codigo" => $b->getCodigo(),
            "matricula" => $b->getMatricula(),
            "empresaId" => $b->getEmpresa()?->getId(),
        ];
    }

    /** Referencia corta para mensajes: `{ id, fecha, ruta, bus }`. @return array<string, mixed> */
    public static function referencia(Salida $s): array
    {
        return [
            "id" => $s->getId(),
            "fecha" => $s->getFecha()?->format(DATE_ATOM),
            "ruta" => self::ruta($s->getTrayecto()),
            "bus" => $s->getBus()?->getCodigo(),
        ];
    }

    /** @return array<string, mixed> */
    public static function fila(Salida $s, int $vendidos, ?int $capacidad, \DateTimeInterface $ahora): array
    {
        return [
            "id" => $s->getId(),
            "fecha" => $s->getFecha()?->format(DATE_ATOM),
            "estado" => $s->getEstado()->value,
            "atrasada" => $s->getEstado() === EstadoSalida::PROGRAMADA && $s->getFecha() < $ahora,
            "trayecto" => self::trayecto($s->getTrayecto()),
            "bus" => $s->getBus() ? self::bus($s->getBus()) : null,
            "empresa" => $s->getEmpresa() ? ["id" => $s->getEmpresa()->getId(), "nombre" => $s->getEmpresa()->getNombre()] : null,
            "vendidos" => $vendidos,
            "capacidad" => $capacidad,
        ];
    }
}
