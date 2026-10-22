<?php

declare(strict_types=1);

namespace App\Acceso;

/**
 * Dónde puede un usuario ejercer una capacidad (puro, ADR-039): la unión de
 * los ámbitos de sus asignaciones vigentes que la conceden. La unión de
 * rectángulos no es un rectángulo: para filtrar listados hay que usar
 * `condicion()` (un OR de ámbitos) y no el producto de `empresas()` por
 * `estaciones()`.
 */
final class Alcance
{
    /** @var list<Ambito> */
    private readonly array $ambitos;

    /** @param list<Ambito> $ambitos */
    public function __construct(array $ambitos)
    {
        $this->ambitos = array_values(array_filter($ambitos, static fn(Ambito $a) => !$a->esVacio()));
    }

    public static function nada(): self
    {
        return new self([]);
    }

    public static function total(): self
    {
        return new self([Ambito::total()]);
    }

    /** @return list<Ambito> */
    public function ambitos(): array
    {
        return $this->ambitos;
    }

    public function vacio(): bool
    {
        return $this->ambitos === [];
    }

    /** Algún ámbito sobre todas las empresas y estaciones, sin agencia. */
    public function sinRestriccion(): bool
    {
        foreach ($this->ambitos as $a) {
            if ($a->esTotal()) {
                return true;
            }
        }

        return false;
    }

    public function contiene(Ubicacion $u): bool
    {
        foreach ($this->ambitos as $a) {
            if ($a->contiene($u)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int>|null las empresas de algún ámbito; null = todas */
    public function empresas(): ?array
    {
        return $this->union(static fn(Ambito $a) => $a->empresas);
    }

    /** @return list<int>|null las estaciones de algún ámbito; null = todas */
    public function estaciones(): ?array
    {
        return $this->union(static fn(Ambito $a) => $a->estaciones);
    }

    /** La única empresa del alcance, si es una sola. */
    public function empresaUnica(): ?int
    {
        $empresas = $this->empresas();

        return $empresas !== null && count($empresas) === 1 ? $empresas[0] : null;
    }

    /** La única estación del alcance, si es una sola. */
    public function estacionUnica(): ?int
    {
        $estaciones = $this->estaciones();

        return $estaciones !== null && count($estaciones) === 1 ? $estaciones[0] : null;
    }

    /**
     * `$destino` cabe en la unión de estos ámbitos (para otorgar sin escalar
     * privilegios). Celda por celda cuando ambas dimensiones son finitas;
     * con "todas" en una, cada elemento de la otra necesita un ámbito con
     * "todas" en esa dimensión.
     */
    public function cubre(Ambito $destino): bool
    {
        foreach ($this->ambitos as $a) {
            if ($a->cubre($destino)) {
                return true;
            }
        }
        $empresas = $destino->empresas;
        $estaciones = $destino->estaciones;
        if ($empresas === null && $estaciones === null) {
            return false;
        }
        foreach ($empresas ?? [null] as $empresa) {
            foreach ($estaciones ?? [null] as $estacion) {
                if (!$this->cubreCelda($empresa, $estacion, $destino->agencia)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Condición SQL (DQL o DBAL) con los ámbitos: `[condición, parámetros]`;
     * los parámetros son listas de enteros (`ArrayParameterType::INTEGER`).
     * Sin columna de estación, solo cuentan las empresas. Con `$compartidos`
     * también entran los registros sin empresa.
     *
     * @return array{0: string, 1: array<string, list<int>>}
     */
    public function condicion(string $empresa, ?string $estacion = null, bool $compartidos = false, string $prefijo = 'alcance'): array
    {
        $partes = [];
        $parametros = [];
        foreach ($this->ambitos as $i => $a) {
            $c = [];
            if ($a->empresas !== null) {
                $c[] = $compartidos
                    ? sprintf('(%s IN (:%s_e%d) OR %s IS NULL)', $empresa, $prefijo, $i, $empresa)
                    : sprintf('%s IN (:%s_e%d)', $empresa, $prefijo, $i);
                $parametros["{$prefijo}_e$i"] = $a->empresas;
            }
            if ($estacion !== null && $a->estaciones !== null) {
                $c[] = sprintf('%s IN (:%s_s%d)', $estacion, $prefijo, $i);
                $parametros["{$prefijo}_s$i"] = $a->estaciones;
            }
            if ($c === []) {
                return ['1 = 1', []];
            }
            $partes[] = '(' . implode(' AND ', $c) . ')';
        }

        return $partes === [] ? ['1 = 0', []] : ['(' . implode(' OR ', $partes) . ')', $parametros];
    }

    /** @return list<array{empresas: list<int>|null, estaciones: list<int>|null, agencia: int|null}> */
    public function toArray(): array
    {
        return array_map(static fn(Ambito $a) => $a->toArray(), $this->ambitos);
    }

    private function cubreCelda(?int $empresa, ?int $estacion, ?int $agencia): bool
    {
        foreach ($this->ambitos as $a) {
            if ($a->cubreCelda($empresa, $estacion, $agencia)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(Ambito): (list<int>|null) $de
     *
     * @return list<int>|null
     */
    private function union(callable $de): ?array
    {
        $ids = [];
        foreach ($this->ambitos as $a) {
            $parte = $de($a);
            if ($parte === null) {
                return null;
            }
            $ids = [...$ids, ...$parte];
        }
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
