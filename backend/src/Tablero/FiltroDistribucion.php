<?php

declare(strict_types=1);

namespace App\Tablero;

use App\Excepcion\ParametroInvalido;
use App\Http\Parametros;

/**
 * Días de venta (ambos incluidos) y empresas de la distribución de la venta.
 * Sin `desde`, hoy; sin `hasta`, un solo día. Sin empresas, todas las del
 * alcance.
 */
final readonly class FiltroDistribucion
{
    /** Un año: lo más largo que se agrega en la petición (no hay copia, el rango es libre). */
    public const MAX_DIAS = 366;

    /**
     * @param list<int> $empresas ids sin repetir y en orden; vacío = todas las del alcance
     */
    public function __construct(
        public \DateTimeImmutable $desde,
        public \DateTimeImmutable $hasta,
        public array $empresas = [],
    ) {
        if ($hasta < $desde) {
            throw new ParametroInvalido("La fecha final no puede ser anterior a la inicial.", "rango_invalido");
        }
        if ($desde->diff($hasta)->days >= self::MAX_DIAS) {
            throw new ParametroInvalido(sprintf("El rango no puede pasar de %d días.", self::MAX_DIAS), "rango_invalido");
        }
    }

    /** @param array<string, mixed> $q `empresas`: ids separados por coma (`1,3`) */
    public static function desdeQuery(array $q, \DateTimeImmutable $ahora): self
    {
        $desde = Parametros::dia($q["desde"] ?? null, "la fecha inicial") ?? $ahora->setTime(0, 0);
        $texto = is_scalar($q["empresas"] ?? null) ? (string) $q["empresas"] : "";
        $empresas = [];
        foreach (array_filter(explode(",", $texto), static fn(string $t) => trim($t) !== "") as $t) {
            $empresas[] = (int) Parametros::entero($t, "el filtro de empresas");
        }
        $empresas = array_values(array_unique($empresas));
        sort($empresas);

        return new self($desde, Parametros::dia($q["hasta"] ?? null, "la fecha final") ?? $desde, $empresas);
    }

    /** Primer instante después del rango. */
    public function fin(): \DateTimeImmutable
    {
        return $this->hasta->modify("+1 day");
    }
}
