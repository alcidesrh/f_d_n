<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Acceso\Catalogo\CatalogoRecursos;
use App\Acceso\Catalogo\DefinicionRecurso;
use App\Acceso\PermisosUsuario;
use App\Entity\VueRoute;

/**
 * Una pantalla del frontend y lo que exige para abrirla (ADR-039, ADR-049),
 * tal como la declara su ruta (`meta.capacidad` y `meta.recurso`) y llega por
 * la sincronización de rutas. Puro: la misma regla que aplica el router del
 * frontend (`core/acceso/reglas.ts`, `puedeEntrar`).
 *
 * - `capacidades`: alguna de ellas en algún ámbito; sin ninguna, la abre
 *   cualquiera con sesión.
 * - `recurso`: pantalla del CRUD genérico; la entidad sale del parámetro
 *   `parametro` (`bus-clase` → `BusClase`) y exige `operacion` sobre ella
 *   (con el parámetro `registro` presente, abre uno que ya existe: basta
 *   con verla). Sin la entidad es el selector y exige `selector`.
 */
final class Pantalla
{
    /**
     * @param list<string>                                                                          $capacidades
     * @param array{parametro: string, operacion: string, registro?: string, selector?: string}|null $recurso
     */
    public function __construct(
        public readonly string $nombre,
        public readonly ?string $path,
        public readonly array $capacidades = [],
        public readonly ?array $recurso = null,
    ) {}

    public static function de(VueRoute $ruta, ?string $path = null): self
    {
        return new self((string) $ruta->getVueRouteName(), $path ?? $ruta->getPath(), $ruta->getCapacidades(), $ruta->getRecurso());
    }

    /** @param array<string, string> $parametros */
    public function admite(PermisosUsuario $permisos, CatalogoRecursos $recursos, array $parametros = []): bool
    {
        if ($this->capacidades !== [] && !array_any($this->capacidades, static fn(string $c) => $permisos->tiene($c))) {
            return false;
        }
        if ($this->recurso === null) {
            return true;
        }
        $entidad = trim((string) ($parametros[$this->recurso['parametro']] ?? ''));
        if ($entidad === '') {
            $selector = $this->recurso['selector'] ?? null;

            return $selector === null || $permisos->tiene($selector);
        }
        $definicion = $recursos->de(self::entidad($entidad));
        if ($definicion === null) {
            return false;
        }
        $registro = $this->recurso['registro'] ?? null;
        $operacion = $registro !== null && trim((string) ($parametros[$registro] ?? '')) !== '' ? DefinicionRecurso::VER : $this->recurso['operacion'];

        return $definicion->permite($permisos, $operacion);
    }

    /** `bus-clase` (parámetro de `/lista/:entity`) → `BusClase` (la entidad), como `recursoDeRuta` del frontend. */
    public static function entidad(string $parametro): string
    {
        return implode('', array_map(ucfirst(...), array_filter(preg_split('/[-_]/', $parametro) ?: [], static fn(string $p) => $p !== '')));
    }
}
