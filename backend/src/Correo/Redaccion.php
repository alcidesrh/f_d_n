<?php

declare(strict_types=1);

namespace App\Correo;

/**
 * Reglas puras de la redacción: el asunto, la cita y los destinatarios de
 * una respuesta o un reenvío, el HTML que acompaña al texto y el extracto
 * de la lista.
 */
final class Redaccion
{
    public const RESPONDER = "responder";
    public const RESPONDER_TODOS = "responderTodos";
    public const REENVIAR = "reenviar";
    public const MODOS = [self::RESPONDER, self::RESPONDER_TODOS, self::REENVIAR];

    public static function asunto(string $asunto, string $modo): string
    {
        $prefijo = $modo === self::REENVIAR ? "Rv:" : "Re:";
        $patron = $modo === self::REENVIAR ? '/^\s*(rv|fw|fwd|reenv)\s*:/i' : '/^\s*(re|r|aw|sv)\s*:/i';

        return preg_match($patron, $asunto) ? trim($asunto) : trim($prefijo . " " . trim($asunto));
    }

    /**
     * La cita bajo el texto nuevo, al estilo de los clientes de correo.
     */
    public static function cita(string $modo, string $de, \DateTimeImmutable $fecha, string $asunto, string $para, string $texto): string
    {
        $cuando = self::fecha($fecha);
        if ($modo === self::REENVIAR) {
            return sprintf("\n\n---------- Mensaje reenviado ----------\nDe: %s\nFecha: %s\nAsunto: %s\nPara: %s\n\n%s", $de, $cuando, $asunto, $para, rtrim($texto));
        }
        $lineas = array_map(static fn(string $l) => $l === "" ? ">" : "> " . $l, explode("\n", str_replace("\r\n", "\n", rtrim($texto))));

        return sprintf("\n\nEl %s, %s escribió:\n%s", $cuando, $de, implode("\n", $lineas));
    }

    /**
     * Destinatarios de una respuesta. Responder: a quien escribió (o a su
     * `Reply-To`); en un enviado, a los mismos destinatarios. A todos: además
     * los demás de Para y Cc, sin uno mismo.
     *
     * @param array{direccion: string, nombre: ?string}       $de
     * @param list<array{direccion: string, nombre: ?string}> $para
     * @param list<array{direccion: string, nombre: ?string}> $cc
     * @param list<string>                                    $propias las direcciones de quien responde
     *
     * @return array{para: list<array{direccion: string, nombre: ?string}>, cc: list<array{direccion: string, nombre: ?string}>}
     */
    public static function destinatarios(string $modo, array $de, ?string $responderA, array $para, array $cc, array $propias, bool $esEnviado): array
    {
        if ($modo === self::REENVIAR) {
            return ["para" => [], "cc" => []];
        }
        $esMia = static fn(array $d) => in_array($d["direccion"], $propias, true);
        $nuevoPara = $esEnviado ? $para : [$responderA !== null ? ["direccion" => $responderA, "nombre" => $responderA === $de["direccion"] ? $de["nombre"] : null] : $de];
        $nuevoCc = [];
        if ($modo === self::RESPONDER_TODOS) {
            $nuevoCc = $esEnviado ? $cc : [...$para, ...$cc];
        }
        $vistos = [];
        $limpiar = static function (array $lista) use (&$vistos, $esMia): array {
            $r = [];
            foreach ($lista as $d) {
                if ($esMia($d) || isset($vistos[$d["direccion"]])) {
                    continue;
                }
                $vistos[$d["direccion"]] = true;
                $r[] = $d;
            }

            return $r;
        };
        $p = $limpiar($nuevoPara);
        // Si escribí yo y respondo a mí mismo, que no quede vacío.
        if ($p === [] && !$esEnviado) {
            $p = [$de];
        }

        return ["para" => $p, "cc" => $limpiar($nuevoCc)];
    }

    /** El texto como HTML sencillo (escapado, saltos de línea y citas grises). */
    public static function html(string $texto): string
    {
        $bloques = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $texto)) as $linea) {
            $citada = str_starts_with($linea, ">");
            $contenido = htmlspecialchars($citada ? ltrim(substr($linea, 1)) : $linea, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $contenido = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $contenido) ?? $contenido;
            $bloques[] = $citada ? '<span style="color:#64748b">' . $contenido . "</span>" : $contenido;
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#0f172a">' . implode("<br>\n", $bloques) . "</div>";
    }

    /** Texto plano de un HTML (para los que llegan sin parte de texto). */
    public static function textoDeHtml(string $html): string
    {
        $sinEstilos = preg_replace('~<(script|style|head)[^>]*>.*?</\1>~is', "", $html) ?? $html;
        $conSaltos = preg_replace('~<(br|/p|/div|/tr|/h[1-6]|/li)[^>]*>~i', "\n", $sinEstilos) ?? $sinEstilos;
        $texto = html_entity_decode(strip_tags($conSaltos), ENT_QUOTES | ENT_HTML5, "UTF-8");

        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', " ", $texto) ?? $texto) ?? $texto);
    }

    /** Lo que se ve en la lista: el texto sin la cita ni espacios de más. */
    public static function extracto(string $texto, int $largo = 140): string
    {
        $lineas = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $texto)) as $l) {
            if (str_starts_with(ltrim($l), ">") || preg_match('/^(El .+ escribió:|-{5,} Mensaje reenviado)/u', trim($l))) {
                break;
            }
            $lineas[] = $l;
        }
        $plano = trim(preg_replace('/\s+/u', " ", implode(" ", $lineas)) ?? "");

        return mb_strlen($plano) > $largo ? rtrim(mb_substr($plano, 0, $largo - 1)) . "…" : $plano;
    }

    public static function fecha(\DateTimeImmutable $fecha): string
    {
        $dias = ["dom", "lun", "mar", "mié", "jue", "vie", "sáb"];
        $meses = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sept", "oct", "nov", "dic"];
        $f = $fecha->setTimezone(new \DateTimeZone("America/Guatemala"));

        return sprintf("%s, %d %s %s a las %s", $dias[(int) $f->format("w")], (int) $f->format("j"), $meses[(int) $f->format("n") - 1], $f->format("Y"), $f->format("H:i"));
    }
}
