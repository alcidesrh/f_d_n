<?php

declare(strict_types=1);

namespace App\Correo;

use App\Entity\Enum\CarpetaCorreo;

/**
 * Lo que se pide al listar el buzón: una vista (una carpeta o "destacados",
 * que cruza Entrada y Enviados), marcas que se suman (no leídos, con
 * adjuntos, de la casa o de fuera) y una búsqueda de texto. Puro: lo lee de
 * la query y `Buzon` lo traduce a DQL.
 */
final class Filtro
{
    public const DESTACADOS = "destacados";
    public const INTERNOS = "internos";
    public const EXTERNOS = "externos";
    public const POR_PAGINA = 50;

    public function __construct(
        /** Una carpeta, o null = destacados. */
        public readonly ?CarpetaCorreo $carpeta = CarpetaCorreo::Entrada,
        public readonly bool $noLeidos = false,
        public readonly bool $conAdjuntos = false,
        /** null, `internos` o `externos`. */
        public readonly ?string $origen = null,
        public readonly string $busqueda = "",
        /** Página siguiente: los de id menor que este con la misma fecha o más viejos (cursor). */
        public readonly ?int $antes = null,
    ) {}

    /** @param array<string, mixed> $q */
    public static function deQuery(array $q): self
    {
        $vista = (string) ($q["carpeta"] ?? "entrada");
        $origen = (string) ($q["origen"] ?? "");

        return new self(
            $vista === self::DESTACADOS ? null : (CarpetaCorreo::tryFrom($vista) ?? CarpetaCorreo::Entrada),
            self::si($q["noLeidos"] ?? null),
            self::si($q["adjuntos"] ?? null),
            in_array($origen, [self::INTERNOS, self::EXTERNOS], true) ? $origen : null,
            mb_substr(trim((string) ($q["q"] ?? "")), 0, 100),
            isset($q["antes"]) && (int) $q["antes"] > 0 ? (int) $q["antes"] : null,
        );
    }

    public function esDestacados(): bool
    {
        return $this->carpeta === null;
    }

    /** Palabras de la búsqueda: todas deben aparecer (en asunto, remitente, destinatarios o texto). @return list<string> */
    public function palabras(): array
    {
        return array_values(array_slice(array_filter(preg_split('/\s+/u', mb_strtolower($this->busqueda)) ?: [], static fn(string $p) => $p !== ""), 0, 6));
    }

    private static function si(mixed $v): bool
    {
        return in_array($v, [true, 1, "1", "true", "si"], true);
    }
}
