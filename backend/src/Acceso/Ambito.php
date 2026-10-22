<?php

declare(strict_types=1);

namespace App\Acceso;

/**
 * Sobre qué rige una asignación (puro, ADR-039): un conjunto de empresas por
 * un conjunto de estaciones, cada uno con `null` = todas (también las que se
 * den de alta después). Un usuario de agencia queda atado a su agencia.
 */
final class Ambito
{
    /** @var list<int>|null */
    public readonly ?array $empresas;

    /** @var list<int>|null */
    public readonly ?array $estaciones;

    /**
     * @param list<int>|null $empresas
     * @param list<int>|null $estaciones
     */
    public function __construct(?array $empresas, ?array $estaciones, public readonly ?int $agencia = null)
    {
        $this->empresas = self::normalizar($empresas);
        $this->estaciones = self::normalizar($estaciones);
    }

    public static function total(): self
    {
        return new self(null, null);
    }

    public function esTotal(): bool
    {
        return $this->empresas === null && $this->estaciones === null && $this->agencia === null;
    }

    /** No cubre nada: le falta empresa o estación. */
    public function esVacio(): bool
    {
        return $this->empresas === [] || $this->estaciones === [];
    }

    public function contiene(Ubicacion $u): bool
    {
        if ($u->conEmpresa && $this->empresas !== null && ($u->empresa === null || !in_array($u->empresa, $this->empresas, true))) {
            return false;
        }
        if ($u->conEstacion && $this->estaciones !== null && ($u->estacion === null || !in_array($u->estacion, $this->estaciones, true))) {
            return false;
        }

        return !($u->conAgencia && $this->agencia !== null && $u->agencia !== $this->agencia);
    }

    /** `$otro` cabe entero en este. */
    public function cubre(self $otro): bool
    {
        return self::incluye($this->empresas, $otro->empresas)
            && self::incluye($this->estaciones, $otro->estaciones)
            && ($this->agencia === null || $this->agencia === $otro->agencia);
    }

    /** La celda (empresa, estación) cae aquí; `null` es "todas" y solo lo cubre "todas". */
    public function cubreCelda(?int $empresa, ?int $estacion, ?int $agencia = null): bool
    {
        return ($this->empresas === null || ($empresa !== null && in_array($empresa, $this->empresas, true)))
            && ($this->estaciones === null || ($estacion !== null && in_array($estacion, $this->estaciones, true)))
            && ($this->agencia === null || $this->agencia === $agencia);
    }

    /** @return array{empresas: list<int>|null, estaciones: list<int>|null, agencia: int|null} */
    public function toArray(): array
    {
        return ['empresas' => $this->empresas, 'estaciones' => $this->estaciones, 'agencia' => $this->agencia];
    }

    /**
     * @param list<int>|null $conjunto
     * @param list<int>|null $parte
     */
    private static function incluye(?array $conjunto, ?array $parte): bool
    {
        return $conjunto === null || ($parte !== null && array_diff($parte, $conjunto) === []);
    }

    /**
     * @param list<int|string>|null $ids
     *
     * @return list<int>|null
     */
    private static function normalizar(?array $ids): ?array
    {
        if ($ids === null) {
            return null;
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }
}
