<?php

declare(strict_types=1);

namespace App\Acceso;

/**
 * Administración delegada sin escalada de privilegios (puro, ADR-039):
 *
 * - Otorgar, modificar o revocar una asignación exige `iam.asignar` con un
 *   alcance que cubra el destino, y tener cada capacidad del rol en todo
 *   ese ámbito. El rol de sistema solo lo otorga quien lo tiene.
 * - Nadie toca sus propias asignaciones.
 * - Sobre otro usuario (contraseña, datos, activarlo, borrarlo) solo actúa
 *   quien podría otorgarle cada una de sus asignaciones vigentes.
 * - Agregarle a un rol una capacidad (o aflojarle un límite) exige poder
 *   otorgarla en cada ámbito donde el rol ya está asignado.
 *
 * Cada regla devuelve el motivo del rechazo, o null si se permite.
 */
final class Delegacion
{
    /**
     * @param list<string>             $capacidadesRol
     * @param callable(string): string $nombre de una capacidad, para el mensaje
     */
    public static function rechazoOtorgar(
        PermisosUsuario $actor,
        int $actorId,
        int $usuarioId,
        array $capacidadesRol,
        bool $rolSistema,
        Ambito $destino,
        callable $nombre,
    ): ?string {
        if ($actorId === $usuarioId) {
            return 'Nadie puede cambiar sus propias asignaciones: pídaselo a otro administrador.';
        }
        if (!$actor->alcance(Capacidad::IAM_ASIGNAR->value)->cubre($destino)) {
            return 'Su alcance para asignar roles no cubre esas empresas y estaciones.';
        }
        if ($rolSistema) {
            return $actor->sistema() ? null : 'Solo un administrador del sistema otorga el rol de sistema.';
        }

        return self::faltante($actor, $capacidadesRol, $destino, $nombre);
    }

    /**
     * El actor puede otorgar cada asignación vigente del usuario.
     *
     * @param list<Concesion>          $concesiones del usuario sobre el que actúa
     * @param callable(string): string $nombre
     */
    public static function rechazoSobreUsuario(PermisosUsuario $actor, int $actorId, int $usuarioId, array $concesiones, callable $nombre): ?string
    {
        if ($actorId === $usuarioId) {
            return null;
        }
        foreach ($concesiones as $c) {
            if ($c->sistema && !$actor->sistema()) {
                return 'Es administrador del sistema: solo otro administrador del sistema puede hacerlo.';
            }
            if (!$c->sistema && ($motivo = self::faltante($actor, $c->capacidades, $c->ambito, $nombre)) !== null) {
                return sprintf('Tiene el rol «%s», con permisos que usted no puede otorgar: %s', $c->rolNombre, lcfirst($motivo));
            }
        }

        return null;
    }

    /**
     * @param list<string>             $antes
     * @param list<string>             $despues
     * @param array<string, int>       $limitesAntes
     * @param array<string, int>       $limitesDespues
     * @param list<Ambito>             $asignado      ámbitos de las asignaciones vigentes del rol
     * @param callable(string): string $nombre
     */
    public static function rechazoEditarRol(
        PermisosUsuario $actor,
        array $antes,
        array $despues,
        array $limitesAntes,
        array $limitesDespues,
        array $asignado,
        callable $nombre,
    ): ?string {
        $agregadas = array_diff($despues, $antes);
        foreach (Limite::cases() as $l) {
            $previo = $limitesAntes[$l->value] ?? null;
            $nuevo = $limitesDespues[$l->value] ?? null;
            $capacidad = $l->capacidad()->value;
            if (in_array($capacidad, $despues, true) && $previo !== null && ($nuevo === null || $nuevo > $previo)) {
                $agregadas[] = $capacidad;
            }
        }
        foreach (array_unique($agregadas) as $c) {
            if (!$actor->tiene($c)) {
                return sprintf('No puede agregar «%s» a un rol: usted no la tiene.', $nombre($c));
            }
            foreach ($asignado as $ambito) {
                if (!$actor->alcance($c)->cubre($ambito)) {
                    return sprintf('No puede agregar «%s»: el rol está asignado donde usted no la tiene.', $nombre($c));
                }
            }
        }

        return null;
    }

    /**
     * @param list<string>             $capacidades
     * @param callable(string): string $nombre
     */
    private static function faltante(PermisosUsuario $actor, array $capacidades, Ambito $destino, callable $nombre): ?string
    {
        foreach ($capacidades as $c) {
            if (!$actor->alcance($c)->cubre($destino)) {
                return sprintf('No puede otorgar «%s» ahí: usted no la tiene en todo ese alcance.', $nombre($c));
            }
        }

        return null;
    }
}
