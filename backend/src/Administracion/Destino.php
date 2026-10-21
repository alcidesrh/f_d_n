<?php

declare(strict_types=1);

namespace App\Administracion;

/**
 * A dónde lleva un enlace de la administración: una ruta del frontend por
 * nombre (`entity-list` + `{ entity: "bus" }`) o un path escrito a mano
 * (`/reporte/ventas?dias=7`, o una URL externa `https://…`). Puro.
 */
final class Destino
{
    private const RUTA = '/^[A-Za-z0-9_.:-]{1,120}$/';
    private const PARAMETRO = '/^[A-Za-z_][A-Za-z0-9_]{0,40}$/';

    /** @param array<string, string> $parametros */
    private function __construct(
        public readonly ?string $ruta,
        public readonly array $parametros,
        public readonly ?string $path,
    ) {}

    public static function ruta(string $ruta, array $parametros = []): self
    {
        $ruta = trim($ruta);
        if (!preg_match(self::RUTA, $ruta)) {
            throw new AdministracionRechazada("La ruta «{$ruta}» no es válida.", "ruta_invalida");
        }
        $limpios = [];
        foreach ($parametros as $nombre => $valor) {
            if (!is_string($nombre) || !preg_match(self::PARAMETRO, $nombre)) {
                throw new AdministracionRechazada("El parámetro «{$nombre}» no es válido.", "parametro_invalido");
            }
            if (!is_scalar($valor) || trim((string) $valor) === "") {
                throw new AdministracionRechazada("Falta el valor del parámetro «{$nombre}».", "parametro_invalido");
            }
            $limpios[$nombre] = mb_substr(trim((string) $valor), 0, 120);
        }

        return new self($ruta, $limpios, null);
    }

    public static function path(string $path): self
    {
        $path = trim($path);
        $interno = str_starts_with($path, "/") && !str_starts_with($path, "//");
        $externo = (bool) preg_match('#^https?://[^\s/$.?\#].[^\s]*$#i', $path);
        if (!($interno || $externo) || mb_strlen($path) > 500 || preg_match('/\s/', $path)) {
            throw new AdministracionRechazada("El path debe empezar con «/» (o ser una dirección http/https) y no llevar espacios.", "path_invalido");
        }

        return new self(null, [], $path);
    }

    /**
     * `{ ruta, parametros? }`, `{ path }` o nada (null): sin destino.
     *
     * @param mixed $datos
     */
    public static function desde(mixed $datos): ?self
    {
        if ($datos === null || $datos === []) {
            return null;
        }
        if (!is_array($datos)) {
            throw new AdministracionRechazada("El destino no es válido.", "destino_invalido");
        }
        $ruta = $datos["ruta"] ?? null;
        $path = $datos["path"] ?? null;
        if (is_string($ruta) && trim($ruta) !== "") {
            $parametros = $datos["parametros"] ?? [];

            return self::ruta($ruta, is_array($parametros) ? $parametros : []);
        }
        if (is_string($path) && trim($path) !== "") {
            return self::path($path);
        }

        return null;
    }

    /** @return array{ruta: ?string, parametros: object, path: ?string} `parametros` siempre objeto en JSON. */
    public function toArray(): array
    {
        return ["ruta" => $this->ruta, "parametros" => (object) $this->parametros, "path" => $this->path];
    }
}
