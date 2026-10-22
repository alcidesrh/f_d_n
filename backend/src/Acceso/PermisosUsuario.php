<?php

declare(strict_types=1);

namespace App\Acceso;

/**
 * Lo que puede hacer un usuario y dónde (puro, ADR-039): la unión de sus
 * concesiones vigentes. No hay negaciones: cada "sí" lo explica una sola
 * asignación (`origen()`).
 */
final class PermisosUsuario
{
    /** @var array<string, Alcance> */
    private array $alcances = [];

    /**
     * @param list<Concesion> $concesiones
     * @param list<string>    $catalogo    las capacidades que existen (las tiene el rol de sistema)
     */
    public function __construct(
        private readonly array $concesiones,
        private readonly array $catalogo,
    ) {}

    public static function ninguno(): self
    {
        return new self([], []);
    }

    /** @return list<Concesion> */
    public function concesiones(): array
    {
        return $this->concesiones;
    }

    public function alcance(string $capacidad): Alcance
    {
        return $this->alcances[$capacidad] ??= new Alcance(array_values(array_map(
            static fn(Concesion $c) => $c->ambito,
            array_filter($this->concesiones, fn(Concesion $c) => $c->concede($capacidad, $this->catalogo)),
        )));
    }

    public function tiene(string $capacidad): bool
    {
        return !$this->alcance($capacidad)->vacio();
    }

    /** Con `$ubicacion` null basta con tenerla en algún ámbito. */
    public function puede(string $capacidad, ?Ubicacion $ubicacion = null): bool
    {
        $alcance = $this->alcance($capacidad);

        return $ubicacion === null ? !$alcance->vacio() : $alcance->contiene($ubicacion);
    }

    /** La concesión que explica el permiso (la primera que lo da). */
    public function origen(string $capacidad, ?Ubicacion $ubicacion = null): ?Concesion
    {
        foreach ($this->concesiones as $c) {
            if (!$c->ambito->esVacio() && $c->concede($capacidad, $this->catalogo) && ($ubicacion === null || $c->ambito->contiene($ubicacion))) {
                return $c;
            }
        }

        return null;
    }

    /**
     * El límite que rige: el más permisivo entre las concesiones que dan la
     * capacidad ahí. null = sin límite (alguna no lo fija).
     */
    public function limite(string $capacidad, Limite $limite, ?Ubicacion $ubicacion = null): ?int
    {
        $maximo = null;
        foreach ($this->concesiones as $c) {
            if ($c->ambito->esVacio() || !$c->concede($capacidad, $this->catalogo) || ($ubicacion !== null && !$c->ambito->contiene($ubicacion))) {
                continue;
            }
            $valor = $c->sistema ? null : ($c->limites[$limite->value] ?? null);
            if ($valor === null) {
                return null;
            }
            $maximo = max($maximo ?? 0, $valor);
        }

        return $maximo;
    }

    /** @return array<string, Alcance> las capacidades que tiene, con su alcance */
    public function capacidades(): array
    {
        $codigos = [];
        foreach ($this->concesiones as $c) {
            if (!$c->ambito->esVacio()) {
                $codigos = [...$codigos, ...($c->sistema ? $this->catalogo : $c->capacidades)];
            }
        }
        $resultado = [];
        foreach (array_unique($codigos) as $codigo) {
            $resultado[$codigo] = $this->alcance($codigo);
        }
        ksort($resultado);

        return $resultado;
    }

    /** Todos sus ámbitos, de cualquier rol: el piso de visibilidad por empresa y las estaciones donde trabaja. */
    public function general(): Alcance
    {
        return new Alcance(array_map(static fn(Concesion $c) => $c->ambito, $this->concesiones));
    }

    public function sistema(): bool
    {
        foreach ($this->concesiones as $c) {
            if ($c->sistema && !$c->ambito->esVacio()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> los roles que tiene con una asignación vigente */
    public function roles(): array
    {
        return array_values(array_unique(array_map(static fn(Concesion $c) => $c->rol, $this->concesiones)));
    }

    /** Cambia si cambia cualquier cosa de lo que puede (el frontend la compara para refrescar). */
    public function huella(): string
    {
        return sha1(json_encode(array_map(static fn(Concesion $c) => [
            $c->asignacion, $c->rol, $c->sistema, $c->capacidades, $c->limites, $c->ambito->toArray(),
        ], $this->concesiones), JSON_THROW_ON_ERROR));
    }
}
