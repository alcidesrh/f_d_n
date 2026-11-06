<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

/**
 * Reglas puras de la suplantación: quién puede entrar como quién y qué
 * peticiones no se dejan hacer mientras se suplanta.
 */
final class ReglasSuplantacion
{
    public const MOTIVO_MIN = 5;
    public const MOTIVO_MAX = 255;
    public const DURACION_MIN = 5;
    public const DURACION_MAX = 480;

    /**
     * Rutas que nunca se hacen como otro: credenciales, datos de la cuenta,
     * accesos y el token propio del usuario. Método (o `*`) y patrón.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const SIEMPRE_PROHIBIDAS = [
        ["POST", '#^/auth$#'],
        ["*", '#^/api/me/password$#'],
        ["PUT", '#^/api/me/cuenta$#'],
        ["POST", '#^/api/me/foto$#'],
        ["DELETE", '#^/api/me/foto$#'],
        ["POST", '#^/api/usuarios/-?\d+/password$#'],
        ["POST", '#^/api/usuarios/-?\d+/suplantar$#'],
        ["ESCRITURA", '#^/api/acceso/#'],
        ["ESCRITURA", '#^/api/suplantacion/configuracion$#'],
    ];

    /** Lo que la app hace sola en el chat: se ignora sin error en vez de prohibirlo. */
    private const AUTOMATICAS_CHAT = ['#^/api/chat/presencia$#', '#^/api/chat/canales/\d+/leido$#', '#^/api/chat/push$#'];

    /**
     * Por qué el actor no puede entrar como el objetivo; null si puede.
     */
    public static function rechazo(
        bool $activa,
        bool $actorEsSistema,
        bool $actorYaSuplanta,
        bool $mismoUsuario,
        bool $objetivoActivo,
        bool $objetivoEsSistema,
        bool $exigeMotivo,
        ?string $motivo,
    ): ?SuplantacionRechazada {
        return match (true) {
            !$activa => new SuplantacionRechazada("La suplantación de usuarios está desactivada en la configuración.", "suplantacion_desactivada", 403),
            !$actorEsSistema => new SuplantacionRechazada("Solo un administrador del sistema puede entrar como otro usuario.", "solo_sistema", 403),
            $actorYaSuplanta => new SuplantacionRechazada("Ya está usando la cuenta de otro usuario: vuelva a la suya primero.", "ya_suplanta", 409),
            $mismoUsuario => new SuplantacionRechazada("No puede suplantarse a sí mismo.", "mismo_usuario"),
            !$objetivoActivo => new SuplantacionRechazada("El usuario está desactivado.", "usuario_inactivo"),
            $objetivoEsSistema => new SuplantacionRechazada("No se puede entrar como otro administrador del sistema.", "objetivo_sistema", 403),
            $exigeMotivo && mb_strlen(self::motivo($motivo) ?? "") < self::MOTIVO_MIN => new SuplantacionRechazada(sprintf("Escriba el motivo (al menos %d letras).", self::MOTIVO_MIN), "motivo_requerido"),
            default => null,
        };
    }

    /** El motivo limpio y acotado; null si viene vacío. */
    public static function motivo(?string $motivo): ?string
    {
        $motivo = trim(preg_replace('/\s+/u', " ", (string) $motivo) ?? "");

        return $motivo === "" ? null : mb_substr($motivo, 0, self::MOTIVO_MAX);
    }

    /** Qué hacer con esta petición mientras se suplanta. */
    public static function bloqueo(string $metodo, string $ruta, ModoChat $chat): Bloqueo
    {
        $metodo = strtoupper($metodo);
        $escritura = !in_array($metodo, ["GET", "HEAD", "OPTIONS"], true);
        foreach (self::SIEMPRE_PROHIBIDAS as [$m, $patron]) {
            $aplica = $m === "*" || $m === $metodo || ($m === "ESCRITURA" && $escritura);
            if ($aplica && preg_match($patron, $ruta)) {
                return Bloqueo::PROHIBIDO;
            }
        }
        $esChat = str_starts_with($ruta, "/api/chat/") || $ruta === "/api/chat";
        $esCorreo = str_starts_with($ruta, "/api/correo/") || $ruta === "/api/correo";
        if (!$esChat && !$esCorreo) {
            return Bloqueo::NINGUNO;
        }
        // Nunca suscribe el navegador de quien suplanta a las notificaciones del usuario.
        $automatica = $esChat && $escritura && array_filter(self::AUTOMATICAS_CHAT, static fn(string $p) => preg_match($p, $ruta) === 1) !== [];
        if ($automatica && ($chat !== ModoChat::PERMITIR || str_ends_with($ruta, "/push"))) {
            return Bloqueo::IGNORAR;
        }

        return match ($chat) {
            ModoChat::PERMITIR => Bloqueo::NINGUNO,
            ModoChat::LECTURA => $escritura ? Bloqueo::PROHIBIDO : Bloqueo::NINGUNO,
            ModoChat::BLOQUEAR => Bloqueo::PROHIBIDO,
        };
    }

    public static function mensaje(string $ruta, ModoChat $chat): string
    {
        if (str_starts_with($ruta, "/api/chat") || str_starts_with($ruta, "/api/correo")) {
            return $chat === ModoChat::BLOQUEAR
                ? "Mientras usa la cuenta de otro usuario, el chat y el correo no están disponibles."
                : "Mientras usa la cuenta de otro usuario, el chat y el correo son de solo lectura.";
        }

        return "Esto no se puede hacer mientras usa la cuenta de otro usuario.";
    }
}
