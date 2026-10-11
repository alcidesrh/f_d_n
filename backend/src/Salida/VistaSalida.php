<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Bus;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Entity\Ruta;

/** Cómo se ve una salida (y sus partes) en la API de gestión de salidas. */
final class VistaSalida
{
    /** Nombre de la ruta (o `Origen → Destino` si no lo tiene). */
    public static function ruta(Ruta $r): string
    {
        return $r->getNombre() ?: $r->getPuntas();
    }

    /** @return array{id: int, nombre: string, origen: ?string, destino: ?string, paradas: int, duracionMinutos: ?int} */
    public static function rutaOpcion(Ruta $r): array
    {
        return [
            "id" => (int) $r->getId(),
            "nombre" => self::ruta($r),
            "origen" => $r->getOrigen()?->getNombre(),
            "destino" => $r->getDestino()?->getNombre(),
            "paradas" => $r->getParadas()->count(),
            "duracionMinutos" => $r->getDuracionMinutos(),
        ];
    }

    /**
     * `croquisId` y `claseId` sirven para filtrar los buses compatibles al
     * programar (ADR-027).
     *
     * @return array{id: int, codigo: string, matricula: ?string, empresaId: ?int, croquisId: ?int, claseId: ?int}
     */
    public static function bus(Bus $b): array
    {
        return [
            "id" => (int) $b->getId(),
            "codigo" => $b->getCodigo(),
            "matricula" => $b->getMatricula(),
            "empresaId" => $b->getEmpresa()?->getId(),
            "croquisId" => $b->getCroquis()?->getId(),
            "claseId" => $b->getClase()?->getId(),
        ];
    }

    /** Referencia corta para mensajes: `{ id, fecha, ruta, bus }`. @return array<string, mixed> */
    public static function referencia(Salida $s): array
    {
        return [
            "id" => $s->getId(),
            "fecha" => $s->getFecha()?->format(DATE_ATOM),
            "ruta" => self::ruta($s->getRuta()),
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
            "ruta" => self::rutaOpcion($s->getRuta()),
            "bus" => $s->getBus() ? self::bus($s->getBus()) : null,
            "empresa" => $s->getEmpresa() ? ["id" => $s->getEmpresa()->getId(), "nombre" => $s->getEmpresa()->getNombreCorto()] : null,
            "vendidos" => $vendidos,
            "capacidad" => $capacidad,
        ];
    }
}
