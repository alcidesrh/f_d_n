<?php

declare(strict_types=1);

namespace App\Venta\Pago;

/**
 * Resultado de un paso del cobro:
 *
 * - `aprobado`: `autorizacion` es el código del banco.
 * - `dispositivo`: el navegador envía `campos` por POST a `url` en un iframe
 *   oculto (recolección de datos del dispositivo de 3-D Secure) y espera el
 *   aviso (`postMessage`) de alguno de `origenes`; después, continúa.
 * - `autenticacion`: 3-D Secure pide que el cliente se autentique con su
 *   banco; el navegador envía `campos` por POST a `url` (el ACS) en un iframe
 *   visible de `ancho`×`alto`, que al terminar vuelve a `urlRetorno`; después,
 *   continúa.
 * - `rechazado`: `mensaje` explica el motivo con el mayor detalle que dé el banco.
 *
 * `estado` es lo que la pasarela necesita para el siguiente paso (se guarda
 * en el servidor, nunca lo envía el navegador).
 */
final readonly class ResultadoPago
{
    public const APROBADO = "aprobado";
    public const DISPOSITIVO = "dispositivo";
    public const AUTENTICACION = "autenticacion";
    public const RECHAZADO = "rechazado";

    /**
     * @param array<string, string> $campos
     * @param array<string, string> $estado
     * @param list<string>          $origenes
     */
    private function __construct(
        public string $estado,
        public ?string $referenciaPasarela = null,
        public ?string $autorizacion = null,
        public ?string $mensaje = null,
        public ?string $url = null,
        public array $campos = [],
        public array $estadoPasarela = [],
        public array $origenes = [],
        public ?string $ancho = null,
        public ?string $alto = null,
    ) {}

    public static function aprobado(string $referenciaPasarela, string $autorizacion): self
    {
        return new self(self::APROBADO, $referenciaPasarela, $autorizacion);
    }

    /**
     * @param array<string, string> $campos
     * @param array<string, string> $estado
     * @param list<string>          $origenes
     */
    public static function dispositivo(string $url, array $campos, array $estado, array $origenes, ?string $referenciaPasarela = null): self
    {
        return new self(self::DISPOSITIVO, $referenciaPasarela, url: $url, campos: $campos, estadoPasarela: $estado, origenes: $origenes);
    }

    /**
     * @param array<string, string> $campos
     * @param array<string, string> $estado
     */
    public static function autenticacion(string $referenciaPasarela, string $url, array $campos, array $estado = [], string $ancho = "100%", string $alto = "100%"): self
    {
        return new self(self::AUTENTICACION, $referenciaPasarela, url: $url, campos: $campos, estadoPasarela: $estado, ancho: $ancho, alto: $alto);
    }

    public static function rechazado(string $mensaje, ?string $referenciaPasarela = null): self
    {
        return new self(self::RECHAZADO, $referenciaPasarela, mensaje: $mensaje);
    }

    /** Lo que la página necesita para el paso del navegador. */
    public function paraNavegador(): array
    {
        return match ($this->estado) {
            self::DISPOSITIVO => ["estado" => $this->estado, "url" => $this->url, "campos" => $this->campos, "origenes" => $this->origenes],
            self::AUTENTICACION => ["estado" => $this->estado, "url" => $this->url, "campos" => $this->campos, "ancho" => $this->ancho, "alto" => $this->alto],
            default => ["estado" => $this->estado],
        };
    }
}
