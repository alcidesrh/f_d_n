<?php

declare(strict_types=1);

namespace App\Navigation;

/**
 * El path de una ruta de vue-router (`/form/:entity/:id?`,
 * `/rutas/:id(nueva|\d+)`, `/chat/:canal(\d+)?`) como expresión regular, para
 * saber a qué pantalla lleva un enlace escrito a mano y con qué parámetros
 * (puro). Como el router: sin distinguir mayúsculas, con o sin barra final;
 * `?` opcional, `+` y `*` repetibles. Un comodín (`/:pathMatch(.*)*`, la de
 * «no encontrada») no se compara: no es una pantalla de nadie.
 */
final class PatronRuta
{
    private function __construct(
        private readonly string $regex,
        /** Más alto = más específico (los tramos fijos pesan más que los parámetros). */
        public readonly int $puntaje,
        public readonly bool $comodin,
    ) {}

    public static function de(string $path): self
    {
        $regex = '';
        $puntaje = 0;
        $comodin = false;
        foreach (array_values(array_filter(explode('/', $path), static fn(string $s) => $s !== '')) as $tramo) {
            if (!preg_match('/^:(\w+)(?:\(((?:[^()]|\([^()]*\))*)\))?([?*+])?$/', $tramo, $m)) {
                $regex .= '/' . preg_quote($tramo, '#');
                $puntaje += 4;
                continue;
            }
            $nombre = $m[1];
            $patron = ($m[2] ?? '') !== '' ? $m[2] : '[^/]+';
            $modificador = $m[3] ?? '';
            $comodin = $comodin || $patron === '.*';
            $grupo = in_array($modificador, ['+', '*'], true)
                ? "(?P<{$nombre}>(?:{$patron})(?:/(?:{$patron}))*)"
                : "(?P<{$nombre}>{$patron})";
            $regex .= in_array($modificador, ['?', '*'], true) ? "(?:/{$grupo})?" : "/{$grupo}";
            $puntaje += ($m[2] ?? '') !== '' ? 2 : 1;
        }

        return new self('#^' . $regex . '/?$#iu', $puntaje, $comodin);
    }

    /**
     * Los parámetros si el path (sin consulta ni fragmento) es de esta ruta;
     * null si no.
     *
     * @return array<string, string>|null
     */
    public function comparar(string $path): ?array
    {
        if ($this->comodin || @preg_match($this->regex, $path, $m) !== 1) {
            return null;
        }
        $parametros = [];
        foreach ($m as $clave => $valor) {
            if (is_string($clave) && $valor !== '') {
                $parametros[$clave] = rawurldecode($valor);
            }
        }

        return $parametros;
    }

    /** `/lista/bus?x=1#a` → `/lista/bus`. */
    public static function sinConsulta(string $path): string
    {
        return (string) preg_replace('/[?#].*$/s', '', $path);
    }
}
