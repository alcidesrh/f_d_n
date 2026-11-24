<?php

declare(strict_types=1);

namespace App\Tablero;

use App\Excepcion\ParametroInvalido;
use App\Http\Parametros;

/**
 * Días de venta (ambos incluidos) y empresa de la distribución de la venta.
 * Sin `desde`, hoy; sin `hasta`, un solo día. Sin empresa, todas las del
 * alcance.
 */
final readonly class FiltroDistribucion
{
    /** Un año: lo más largo que se agrega en la petición (no hay copia, el rango es libre). */
    public const MAX_DIAS = 366;

    public function __construct(
        public \DateTimeImmutable $desde,
        public \DateTimeImmutable $hasta,
        public ?int $empresaId = null,
    ) {
        if ($hasta < $desde) {
            throw new ParametroInvalido("La fecha final no puede ser anterior a la inicial.", "rango_invalido");
        }
        if ($desde->diff($hasta)->days >= self::MAX_DIAS) {
            throw new ParametroInvalido(sprintf("El rango no puede pasar de %d días.", self::MAX_DIAS), "rango_invalido");
        }
    }

    /** @param array<string, mixed> $q */
    public static function desdeQuery(array $q, \DateTimeImmutable $ahora): self
    {
        $desde = Parametros::dia($q["desde"] ?? null, "la fecha inicial") ?? $ahora->setTime(0, 0);

        return new self(
            $desde,
            Parametros::dia($q["hasta"] ?? null, "la fecha final") ?? $desde,
            Parametros::entero($q["empresa"] ?? null, "la empresa"),
        );
    }

    /** Primer instante después del rango. */
    public function fin(): \DateTimeImmutable
    {
        return $this->hasta->modify("+1 day");
    }
}
