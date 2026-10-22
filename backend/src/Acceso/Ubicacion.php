<?php

declare(strict_types=1);

namespace App\Acceso;

/**
 * Dónde cae una operación, para compararla con el alcance de una asignación
 * (puro, ADR-039). Cada dimensión puede no aplicar:
 *
 * - empresa: la dueña del registro; `null` con la dimensión puesta es un
 *   registro compartido (sin empresa), que solo cubre un alcance sobre todas.
 * - estación: donde ocurre (la de trabajo de quien opera, o donde se vendió);
 *   `null` con la dimensión puesta es "sin estación", que solo cubre un
 *   alcance sobre todas.
 * - agencia: la agencia del registro (una venta, un depósito); un usuario de
 *   agencia solo opera los de la suya.
 */
final class Ubicacion
{
    private function __construct(
        public readonly bool $conEmpresa = false,
        public readonly ?int $empresa = null,
        public readonly bool $conEstacion = false,
        public readonly ?int $estacion = null,
        public readonly bool $conAgencia = false,
        public readonly ?int $agencia = null,
    ) {}

    /** Ninguna dimensión: la cubre cualquier asignación vigente. */
    public static function ninguna(): self
    {
        return new self();
    }

    /** De una empresa; `null` = registro compartido. */
    public static function empresa(?int $empresa): self
    {
        return new self(conEmpresa: true, empresa: $empresa);
    }

    public function conEstacion(?int $estacion): self
    {
        return new self($this->conEmpresa, $this->empresa, true, $estacion, $this->conAgencia, $this->agencia);
    }

    public function conAgencia(?int $agencia): self
    {
        return new self($this->conEmpresa, $this->empresa, $this->conEstacion, $this->estacion, true, $agencia);
    }

    /**
     * La inversa de `toArray()`: cada clave presente es una dimensión puesta
     * (con su valor, que puede ser null).
     *
     * @param array<string, mixed> $datos
     */
    public static function desdeArray(array $datos): self
    {
        $id = static fn(string $clave) => isset($datos[$clave]) && is_numeric($datos[$clave]) ? (int) $datos[$clave] : null;

        return new self(
            array_key_exists('empresa', $datos),
            $id('empresa'),
            array_key_exists('estacion', $datos),
            $id('estacion'),
            array_key_exists('agencia', $datos),
            $id('agencia'),
        );
    }

    /** @return array<string, int|null> */
    public function toArray(): array
    {
        return array_filter([
            'empresa' => $this->conEmpresa ? $this->empresa : false,
            'estacion' => $this->conEstacion ? $this->estacion : false,
            'agencia' => $this->conAgencia ? $this->agencia : false,
        ], static fn($v) => $v !== false);
    }
}
