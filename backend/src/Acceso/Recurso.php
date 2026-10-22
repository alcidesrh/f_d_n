<?php

declare(strict_types=1);

namespace App\Acceso;

/**
 * Cómo protege la API genérica (GraphQL y REST de API Platform) a una
 * entidad (ADR-039). `Api\SeguridadPorDefecto` lo aplica a cada operación
 * sin `security` propia; una entidad publicada sin este atributo queda con
 * sus valores por defecto (capacidades `{snake_case}.ver|editar|eliminar`).
 *
 * - `lecturaLibre`: la lee cualquier usuario con sesión (catálogos de referencia).
 * - `soloLectura`: no se escribe por la API genérica; sus cambios van por los
 *   servicios del dominio (salidas, boletos, ventas…).
 * - `oculto`: ninguna operación genérica (ni leer).
 * - `empresa`: propiedad que lleva a la empresa dueña (`empresa`,
 *   `salida.empresa`; `@id` en la propia `Empresa`). Acota las capacidades
 *   por empresa: listar solo filtra las del alcance; escribir un registro sin
 *   empresa (compartido) exige alcance sobre todas.
 * - `ver`, `editar`, `eliminar`: códigos explícitos, para compartir una
 *   capacidad del dominio (`boleto.ver`, `encomienda.especiales`).
 * - `borrable`: false si se borra por su propia pantalla (usuarios), no por
 *   la API genérica.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Recurso
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $area = 'Otros',
        public readonly ?string $codigo = null,
        public readonly bool $lecturaLibre = false,
        public readonly bool $soloLectura = false,
        public readonly bool $oculto = false,
        public readonly ?string $empresa = null,
        public readonly ?string $ver = null,
        public readonly ?string $editar = null,
        public readonly ?string $eliminar = null,
        public readonly bool $borrable = true,
    ) {}
}
