<?php

declare(strict_types=1);

namespace App\Venta\Pago;

/**
 * Datos del navegador del comprador que 3-D Secure usa para decidir si
 * pide o no el desafío (encabezados de la petición + lo que reporta la página).
 */
final readonly class Navegador
{
    public function __construct(
        public ?string $ip = null,
        public ?string $agente = null,
        public ?string $accept = null,
        public ?string $idioma = null,
        public ?int $anchoPantalla = null,
        public ?int $altoPantalla = null,
        public ?int $profundidadColor = null,
        /** Minutos de diferencia con UTC, como `Date.getTimezoneOffset()`. */
        public ?int $diferenciaHoraria = null,
    ) {}

    /**
     * @param array<string, mixed> $pagina lo que reporta la página (pantalla, zona horaria)
     */
    public static function de(?string $ip, ?string $agente, ?string $accept, ?string $idioma, array $pagina): self
    {
        $entero = static fn(string $k, int $min, int $max) => is_numeric($pagina[$k] ?? null) && (int) $pagina[$k] >= $min && (int) $pagina[$k] <= $max ? (int) $pagina[$k] : null;
        $corto = static fn(?string $v, int $max) => $v !== null && $v !== "" ? mb_substr($v, 0, $max) : null;

        return new self(
            ip: $corto($ip, 45),
            agente: $corto($agente, 2048),
            accept: $corto($accept, 255),
            idioma: $corto(is_string($pagina["idioma"] ?? null) && $pagina["idioma"] !== "" ? $pagina["idioma"] : ($idioma !== null ? trim((string) strtok($idioma, ",;")) : null), 8),
            anchoPantalla: $entero("anchoPantalla", 1, 100000),
            altoPantalla: $entero("altoPantalla", 1, 100000),
            profundidadColor: $entero("profundidadColor", 1, 64),
            diferenciaHoraria: $entero("diferenciaHoraria", -900, 900),
        );
    }
}
