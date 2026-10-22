<?php

declare(strict_types=1);

namespace App\Acceso\Catalogo;

use App\Acceso\Recurso;

/** Una entidad publicada en la API genérica y las capacidades que la protegen (puro). */
final class DefinicionRecurso
{
    public const VER = 'ver';
    public const EDITAR = 'editar';
    public const ELIMINAR = 'eliminar';

    /**
     * @param class-string $clase
     */
    private function __construct(
        public readonly string $clase,
        public readonly string $nombreCorto,
        public readonly string $nombre,
        public readonly string $area,
        /** Capacidad para leer; null si la lee cualquiera con sesión (o si está oculta). */
        public readonly ?string $ver,
        /** Capacidad para crear y modificar; null si no se escribe por la API genérica. */
        public readonly ?string $editar,
        /** Capacidad para eliminar; null si no se escribe por la API genérica. */
        public readonly ?string $eliminar,
        public readonly bool $lecturaLibre,
        public readonly bool $oculto,
        /** Propiedad que lleva a la empresa dueña (`empresa`, `salida.empresa`, `@id`). */
        public readonly ?string $empresa,
        /** Se borra por la API genérica (si no, por su propia pantalla, con `eliminar`). */
        public readonly bool $borrable = true,
    ) {}

    /** @param class-string $clase */
    public static function de(string $clase, ?Recurso $r): self
    {
        $corto = substr($clase, (int) strrpos($clase, '\\') + 1);
        $codigo = $r?->codigo ?? self::snake($corto);
        $oculto = $r?->oculto ?? false;
        $libre = !$oculto && ($r?->lecturaLibre ?? false);
        $escribe = !$oculto && !($r?->soloLectura ?? false);

        return new self(
            clase: $clase,
            nombreCorto: $corto,
            nombre: $r?->nombre ?? $corto,
            area: $r?->area ?? 'Otros',
            ver: $oculto || $libre ? null : ($r?->ver ?? "$codigo." . self::VER),
            editar: $escribe ? ($r?->editar ?? "$codigo." . self::EDITAR) : null,
            eliminar: $escribe ? ($r?->eliminar ?? "$codigo." . self::ELIMINAR) : null,
            lecturaLibre: $libre,
            oculto: $oculto,
            empresa: $r?->empresa,
            borrable: $escribe && ($r?->borrable ?? true),
        );
    }

    /** @return array<string, string> operación (`ver`, `editar`, `eliminar`) → capacidad */
    public function capacidades(): array
    {
        return array_filter([self::VER => $this->ver, self::EDITAR => $this->editar, self::ELIMINAR => $this->eliminar]);
    }

    public static function snake(string $nombre): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $nombre));
    }
}
