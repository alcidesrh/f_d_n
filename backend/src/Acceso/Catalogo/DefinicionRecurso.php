<?php

declare(strict_types=1);

namespace App\Acceso\Catalogo;

use App\Acceso\PermisosUsuario;
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

    /**
     * Puede hacer la operación con la entidad en algún ámbito: leer la de
     * lectura libre cualquiera; lo oculto, nadie. Las filas las acota cada
     * consulta.
     */
    public function permite(PermisosUsuario $permisos, string $operacion): bool
    {
        if ($this->oculto) {
            return false;
        }
        if ($operacion === self::VER && $this->lecturaLibre) {
            return true;
        }
        $capacidad = $this->capacidades()[$operacion] ?? null;

        return $capacidad !== null && $permisos->tiene($capacidad);
    }

    /**
     * Lleva a la navegación (menús y `/administracion`, enmienda 2026-11 de
     * ADR-049): como `permite`, pero leer libre no cuenta. La lectura libre
     * es para que cualquiera lea el catálogo donde lo usa (un selector, la
     * venta); su listado se le muestra a quien puede mantenerlo. Lo que no se
     * escribe por la API genérica (solo lectura) sigue a la vista de todos:
     * no hay a quién reservarlo, se cura con audiencia.
     */
    public function seMuestra(PermisosUsuario $permisos, string $operacion): bool
    {
        if ($operacion === self::VER && $this->lecturaLibre && $this->editar !== null) {
            return $this->permite($permisos, self::EDITAR);
        }

        return $this->permite($permisos, $operacion);
    }

    /** `{ ver, editar, eliminar }` de un usuario (lo que lee el frontend en `/api/me/acceso`). */
    public function permisosDe(PermisosUsuario $permisos): array
    {
        return [
            self::VER => $this->permite($permisos, self::VER),
            self::EDITAR => $this->permite($permisos, self::EDITAR),
            self::ELIMINAR => $this->permite($permisos, self::ELIMINAR),
        ];
    }

    public static function snake(string $nombre): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $nombre));
    }
}
