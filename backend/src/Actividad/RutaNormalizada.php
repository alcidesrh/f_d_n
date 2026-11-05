<?php

declare(strict_types=1);

namespace App\Actividad;

/**
 * La ruta de una petición sin sus ids (`/api/salidas/{id}/croquis`), para
 * agrupar, y el registro que toca: el recurso antes del primer id numérico y
 * ese id. Puro.
 */
final class RutaNormalizada
{
    public const MAX = 120;

    public function __construct(
        public readonly string $ruta,
        public readonly ?string $entidad = null,
        public readonly ?int $registroId = null,
    ) {}

    public static function de(string $path): self
    {
        $segmentos = array_values(array_filter(explode("/", $path), static fn(string $s) => $s !== ""));
        $entidad = null;
        $registro = null;
        $normalizados = [];
        foreach ($segmentos as $i => $s) {
            if (ctype_digit($s)) {
                if ($registro === null && $i > 0) {
                    $registro = strlen($s) <= 18 ? (int) $s : null;
                    $entidad = mb_substr($segmentos[$i - 1], 0, 20);
                }
                $normalizados[] = "{id}";
            } elseif (self::pareceClave($s)) {
                $normalizados[] = "{id}";
            } else {
                $normalizados[] = $s;
            }
        }

        return new self(mb_substr("/" . implode("/", $normalizados), 0, self::MAX), $entidad, $registro);
    }

    /** Un uuid, un hash o un token: no es parte del nombre de la ruta. */
    private static function pareceClave(string $s): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $s)
            || (strlen($s) >= 20 && preg_match('/\d/', $s) && preg_match('/^[A-Za-z0-9_\-=.]+$/', $s));
    }
}
