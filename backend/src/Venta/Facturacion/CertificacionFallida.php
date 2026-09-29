<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

/**
 * El certificador no emitió el DTE. `mensaje` es apto para el usuario (el
 * motivo concreto si el certificador lo dio, si no uno genérico);
 * `recuperable` distingue fallos de comunicación (tiene sentido reintentar)
 * de rechazos del documento (p. ej. NIT del receptor inválido).
 */
final class CertificacionFallida extends \RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly bool $recuperable,
        public readonly ?string $codigo = null,
        ?\Throwable $previa = null,
    ) {
        parent::__construct($mensaje, 0, $previa);
    }

    public static function sinRespuesta(?\Throwable $previa = null): self
    {
        return new self(
            "No se obtuvo respuesta del certificador de facturas. Intente de nuevo en unos segundos.",
            true,
            "sin_respuesta",
            $previa,
        );
    }
}
