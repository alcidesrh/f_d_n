<?php

declare(strict_types=1);

namespace App\Acceso;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;

/** Aplica un `Alcance` a una consulta DQL (ADR-039). */
final class FiltroAlcance
{
    /**
     * Agrega la condición del alcance (un OR de ámbitos); sin restricción no
     * agrega nada. `$o` suma una alternativa (p. ej. "o lo vendió él").
     *
     * @param array<string, mixed> $parametrosO
     */
    public static function aplicar(
        QueryBuilder $qb,
        Alcance $alcance,
        string $empresa,
        ?string $estacion = null,
        bool $compartidos = false,
        string $prefijo = 'alcance',
        ?string $o = null,
        array $parametrosO = [],
    ): QueryBuilder {
        [$condicion, $parametros] = $alcance->condicion($empresa, $estacion, $compartidos, $prefijo);
        if ($condicion === '1 = 1') {
            return $qb;
        }
        $qb->andWhere($o === null ? $condicion : "($condicion OR $o)");
        foreach ($parametros as $nombre => $valor) {
            $qb->setParameter($nombre, $valor, ArrayParameterType::INTEGER);
        }
        foreach ($parametrosO as $nombre => $valor) {
            $qb->setParameter($nombre, $valor);
        }

        return $qb;
    }
}
