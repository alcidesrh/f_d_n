<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Cómo se escriben y se comparan los datos que reconocen a un cliente que
 * vuelve (ADR-068): se guardan limpios y se comparan sin acentos, signos ni
 * mayúsculas. Las expresiones SQL son las de los índices `nat_cliente_*`
 * (migración Version20261122100000): si cambian aquí, cambian allá.
 */
final class IdentidadCliente
{
    /** Nombre, apellido y NIT, sin acentos ni signos: lo que busca la pantalla de venta. */
    public const SQL_CLAVE = "LOWER(REGEXP_REPLACE(TRANSLATE(nombre || ' ' || COALESCE(apellido, '') || ' ' || COALESCE(nit, ''), 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun'), '[^[:alnum:]]+', ' ', 'g'))";

    /** Los últimos 8 dígitos del teléfono: el número sin el código del país. */
    public const SQL_TELEFONO = "RIGHT(REGEXP_REPLACE(telefono, '[^0-9]', '', 'g'), 8)";

    /** El documento sin espacios, guiones ni puntos (los del legado los traen). */
    public const SQL_DOCUMENTO = "REGEXP_REPLACE(UPPER(numero_documento), '[^0-9A-Z]', '', 'g')";

    public const SIGLA_DPI = "DPI";

    private const ACENTOS = ["Á" => "A", "É" => "E", "Í" => "I", "Ó" => "O", "Ú" => "U", "Ü" => "U", "Ñ" => "N",
        "á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ü" => "u", "ñ" => "n"];

    /** Espejo de `SQL_CLAVE` para un texto cualquiera (lo que se escribe en el buscador). */
    public static function clave(string $texto): string
    {
        return trim(mb_strtolower(preg_replace('/[^[:alnum:]]+/u', " ", strtr($texto, self::ACENTOS)) ?? ""));
    }

    /** Nombre o apellido: sin espacios de más ni la cola de signos («GARCIA.-», «PEREZ,,»). */
    public static function nombre(?string $texto): ?string
    {
        $limpio = preg_replace('/\s+/u', " ", trim((string) $texto)) ?? "";
        // La cola de signos se va entera, salvo un punto solo («Jr.», «S.A.»).
        $limpio = preg_replace_callback('/[\s.,;:_-]+$/u', static fn(array $m) => $m[0] === "." ? "." : "", $limpio) ?? "";
        $limpio = ltrim($limpio, " .,;:_-");

        return $limpio === "" ? null : $limpio;
    }

    /** Teléfono: solo dígitos, con el «+» del código del país si lo trae. */
    public static function telefono(?string $texto): ?string
    {
        $texto = trim((string) $texto);
        $digitos = preg_replace('/\D+/', "", $texto) ?? "";
        if ($digitos === "") {
            return null;
        }

        return mb_substr((str_starts_with($texto, "+") ? "+" : "") . $digitos, 0, 15);
    }

    /** Los últimos 8 dígitos (espejo de `SQL_TELEFONO`), o null si no llega a 8. */
    public static function telefonoComparable(?string $texto): ?string
    {
        $digitos = preg_replace('/\D+/', "", (string) $texto) ?? "";

        return strlen($digitos) >= 8 ? substr($digitos, -8) : null;
    }

    /** Número de documento en mayúsculas, sin espacios, guiones ni puntos (espejo de `SQL_DOCUMENTO`). */
    public static function documento(?string $texto): ?string
    {
        $limpio = preg_replace('/[^0-9A-Z]+/', "", mb_strtoupper(strtr(trim((string) $texto), self::ACENTOS))) ?? "";

        return $limpio === "" ? null : mb_substr($limpio, 0, 40);
    }

    /**
     * CUI del DPI: 13 dígitos; los 8 primeros, por su posición (2 a 9), suman
     * un múltiplo de 11 más el noveno, que es el verificador. Los 4 últimos
     * (departamento y municipio) no se revisan: la lista de municipios cambia.
     */
    public static function cuiValido(string $cui): bool
    {
        if (!preg_match('/^\d{13}$/', $cui)) {
            return false;
        }
        $suma = 0;
        for ($i = 0; $i < 8; $i++) {
            $suma += (int) $cui[$i] * ($i + 2);
        }

        return $suma % 11 === (int) $cui[8];
    }
}
