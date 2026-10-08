<?php

declare(strict_types=1);

namespace App\Chat;

/**
 * Quién puede conversar con quién. Replica la organización actual: la
 * administración y las estaciones hablan entre todas; una agencia solo habla
 * con su propia gente y con la administración (nunca con estaciones ni con
 * otras agencias). En un grupo la regla vale para cada par de miembros.
 */
final class Directorio
{
    public static function puedenConversar(Perfil $a, Perfil $b): bool
    {
        if ($a->id === $b->id) {
            return false;
        }
        if ($a->agencia !== null && $b->agencia !== null) {
            return $a->agencia === $b->agencia;
        }
        if ($a->agencia !== null || $b->agencia !== null) {
            $otro = $a->agencia !== null ? $b : $a;

            return $otro->ambito() === Perfil::ADMINISTRACION;
        }

        return true;
    }

    /**
     * Primer par de miembros que no pueden compartir conversación, o null.
     *
     * @param list<Perfil> $miembros
     *
     * @return array{Perfil, Perfil}|null
     */
    public static function parIncompatible(array $miembros): ?array
    {
        $n = count($miembros);
        for ($i = 0; $i < $n; ++$i) {
            for ($j = $i + 1; $j < $n; ++$j) {
                if ($miembros[$i]->id !== $miembros[$j]->id && !self::puedenConversar($miembros[$i], $miembros[$j])) {
                    return [$miembros[$i], $miembros[$j]];
                }
            }
        }

        return null;
    }
}
