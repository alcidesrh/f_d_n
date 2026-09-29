<?php

declare(strict_types=1);

namespace App\Venta\Pago;

/**
 * Resultado de un intento de cobro:
 *
 * - `aprobado`: `autorizacion` es el código del banco.
 * - `autenticacion`: 3-D Secure pide que el cliente se autentique con su
 *   banco; el navegador debe enviar `campos` por POST a `url` (el ACS), que
 *   al terminar vuelve a `urlRetorno` de la solicitud.
 * - `rechazado`: `mensaje` explica el motivo con el mayor detalle que dé el banco.
 */
final readonly class ResultadoPago
{
    public const APROBADO = "aprobado";
    public const AUTENTICACION = "autenticacion";
    public const RECHAZADO = "rechazado";

    /**
     * @param array<string, string> $campos
     */
    private function __construct(
        public string $estado,
        public ?string $referenciaPasarela = null,
        public ?string $autorizacion = null,
        public ?string $mensaje = null,
        public ?string $url = null,
        public array $campos = [],
    ) {}

    public static function aprobado(string $referenciaPasarela, string $autorizacion): self
    {
        return new self(self::APROBADO, $referenciaPasarela, $autorizacion);
    }

    /** @param array<string, string> $campos */
    public static function autenticacion(string $referenciaPasarela, string $url, array $campos): self
    {
        return new self(self::AUTENTICACION, $referenciaPasarela, url: $url, campos: $campos);
    }

    public static function rechazado(string $mensaje, ?string $referenciaPasarela = null): self
    {
        return new self(self::RECHAZADO, $referenciaPasarela, mensaje: $mensaje);
    }
}
