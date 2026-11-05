<?php

declare(strict_types=1);

namespace App\Acceso\Legado;

use App\Acceso\Concesion;
use App\Acceso\Limite;
use App\Acceso\PermisosUsuario;

/**
 * Qué asignaciones de un usuario sobran (puro, ADR-052): las que, quitadas, no
 * cambian lo que puede. El legado daba un rol por privilegio y sus puestos se
 * pisaban; aquí una asignación sobra si otra de las que quedan cubre cada una
 * de sus capacidades en todo su ámbito, sin límite más estricto, y si el
 * usuario sigue en las audiencias donde estaba.
 */
final class ConsolidacionRoles
{
    /**
     * Las de menos capacidades se miran primero y, entre iguales, la más
     * nueva: de dos repetidas queda la primera. Una temporal no se pasa aquí:
     * no cubre a nadie porque se acaba.
     *
     * @param list<Concesion> $concesiones las permanentes del usuario
     * @param list<int>       $tocables    las que se pueden quitar
     * @param list<list<int>> $audiencias  roles de cada menú, caja, enlace o listado
     *
     * @return list<int> ids de las asignaciones que sobran
     */
    public static function sobrantes(array $concesiones, array $tocables, array $audiencias = []): array
    {
        usort($concesiones, static fn(Concesion $a, Concesion $b) => [count($a->capacidades), $b->asignacion] <=> [count($b->capacidades), $a->asignacion]);
        $quedan = $concesiones;
        $sobran = [];
        foreach ($concesiones as $c) {
            if ($c->sistema || !in_array($c->asignacion, $tocables, true)) {
                continue;
            }
            $otras = array_values(array_filter($quedan, static fn(Concesion $o) => $o !== $c));
            if (self::cubierta($c, $otras) && self::sigueEnAudiencias($c, $otras, $audiencias)) {
                $quedan = $otras;
                $sobran[] = $c->asignacion;
            }
        }

        return $sobran;
    }

    /**
     * Las capacidades que cambiaron de ámbito o de límite entre dos estados de
     * un usuario; vacío si puede exactamente lo mismo.
     *
     * @return list<string>
     */
    public static function diferencias(PermisosUsuario $antes, PermisosUsuario $despues): array
    {
        $cambios = [];
        foreach (array_keys($antes->capacidades() + $despues->capacidades()) as $capacidad) {
            $a = $antes->alcance($capacidad);
            $d = $despues->alcance($capacidad);
            foreach ($a->ambitos() as $ambito) {
                if (!$d->cubre($ambito)) {
                    $cambios[$capacidad] = true;
                }
            }
            foreach ($d->ambitos() as $ambito) {
                if (!$a->cubre($ambito)) {
                    $cambios[$capacidad] = true;
                }
            }
        }
        foreach (Limite::cases() as $l) {
            $capacidad = $l->capacidad()->value;
            if ($antes->limite($capacidad, $l) !== $despues->limite($capacidad, $l)) {
                $cambios[$capacidad] = true;
            }
        }

        return array_keys($cambios);
    }

    /** @param list<Concesion> $otras */
    private static function cubierta(Concesion $c, array $otras): bool
    {
        foreach ($c->capacidades as $capacidad) {
            $cubre = static fn(Concesion $o) => $o->concede($capacidad) && $o->ambito->cubre($c->ambito) && self::limitesCubren($o, $c, $capacidad);
            if (array_filter($otras, $cubre) === []) {
                return false;
            }
        }

        return true;
    }

    /** `$o` no la limita más que `$c`: sin valor no hay límite. */
    private static function limitesCubren(Concesion $o, Concesion $c, string $capacidad): bool
    {
        foreach (Limite::cases() as $l) {
            if ($l->capacidad()->value !== $capacidad || $o->sistema) {
                continue;
            }
            $suyo = $o->limites[$l->value] ?? null;
            $propio = $c->limites[$l->value] ?? null;
            if ($suyo !== null && ($propio === null || $suyo < $propio)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Concesion> $otras
     * @param list<list<int>> $audiencias
     */
    private static function sigueEnAudiencias(Concesion $c, array $otras, array $audiencias): bool
    {
        $roles = array_map(static fn(Concesion $o) => $o->rol, $otras);
        foreach ($audiencias as $audiencia) {
            if (in_array($c->rol, $audiencia, true) && !in_array($c->rol, $roles, true) && array_intersect($audiencia, $roles) === []) {
                return false;
            }
        }

        return true;
    }
}
