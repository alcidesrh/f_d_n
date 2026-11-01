<?php

declare(strict_types=1);

namespace App\Correo\Entrante;

use App\Correo\Direcciones;

/**
 * Correo entrante de MailPace (https://docs.mailpace.com/guide/inbound): un
 * POST con JSON por cada correo que llega al dominio de entrada, firmado
 * con Ed25519 (cabecera `X-MailPace-Signature`, firma del cuerpo tal cual,
 * en base64) con la clave pública del dominio (Webhooks → Public Key
 * Verification en app.mailpace.com). Puro: sin base de datos.
 */
final class Mailpace
{
    public const CABECERA_FIRMA = "X-MailPace-Signature";

    /** La firma Ed25519 del cuerpo es de la clave pública (ambas en base64). */
    public static function firmaValida(string $cuerpo, ?string $firma, string $clavePublica): bool
    {
        if ($firma === null || $firma === "" || $clavePublica === "") {
            return false;
        }
        $f = base64_decode(trim($firma), true);
        $k = base64_decode(trim($clavePublica), true);
        if ($f === false || $k === false || strlen($f) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($k) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($f, $cuerpo, $k);
        } catch (\SodiumException) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $datos el JSON del webhook
     *
     * @throws \InvalidArgumentException si falta el remitente
     */
    public static function leer(array $datos): CorreoEntrante
    {
        $de = Direcciones::leer(self::texto($datos, "from"))[0] ?? throw new \InvalidArgumentException("Correo sin remitente válido.");
        $adjuntos = [];
        foreach (is_array($datos["attachments"] ?? null) ? $datos["attachments"] : [] as $a) {
            if (!is_array($a) || !is_string($a["content"] ?? null)) {
                continue;
            }
            $contenido = base64_decode($a["content"], true);
            if ($contenido === false || $contenido === "") {
                continue;
            }
            $cid = is_string($a["cid"] ?? null) && $a["cid"] !== "" ? $a["cid"] : (is_string($a["contentId"] ?? null) ? trim($a["contentId"], "<> ") : null);
            $tipo = strtolower(trim(explode(";", (string) ($a["contentType"] ?? $a["type"] ?? "application/octet-stream"))[0]));
            $adjuntos[] = [
                "nombre" => is_string($a["filename"] ?? null) && trim($a["filename"]) !== "" ? trim($a["filename"]) : ($cid ?? "adjunto"),
                "tipo" => $tipo !== "" ? $tipo : "application/octet-stream",
                "contenido" => $contenido,
                "contentId" => $cid !== "" ? $cid : null,
                "enLinea" => (bool) ($a["related"] ?? false) || (($a["contentDisposition"] ?? "") === "inline" && $cid !== null),
            ];
        }

        return new CorreoEntrante(
            $de,
            Direcciones::leer(self::texto($datos, "to")),
            Direcciones::leer(self::texto($datos, "cc")),
            Direcciones::leer(self::texto($datos, "bcc")),
            Direcciones::leer(self::texto($datos, "replyTo"))[0]["direccion"] ?? null,
            mb_substr(trim(self::texto($datos, "subject") ?? ""), 0, 255),
            self::texto($datos, "text") ?? "",
            self::texto($datos, "html"),
            self::id(self::texto($datos, "messageId")),
            self::id(self::texto($datos, "inReplyTo")),
            self::referencias($datos),
            $adjuntos,
        );
    }

    /** `<abc@x>` → `abc@x`. */
    public static function id(?string $valor): ?string
    {
        $v = trim((string) $valor, " \t\r\n<>");

        return $v !== "" ? $v : null;
    }

    private static function texto(array $datos, string $campo): ?string
    {
        $v = $datos[$campo] ?? null;

        return is_string($v) ? $v : null;
    }

    /** `References` sale de las cabeceras (MailPace no lo da aparte). */
    private static function referencias(array $datos): ?string
    {
        foreach (is_array($datos["headers"] ?? null) ? $datos["headers"] : [] as $h) {
            if (is_string($h) && preg_match('/^references:\s*(.+)$/is', $h, $m)) {
                preg_match_all('/<([^<>]+)>/', $m[1], $ids);

                return $ids[1] !== [] ? implode(" ", $ids[1]) : null;
            }
        }

        return null;
    }
}
