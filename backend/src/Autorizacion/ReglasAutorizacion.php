<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Acceso\Capacidad;
use App\Acceso\PermisosUsuario;
use App\Acceso\Ubicacion;
use App\Entity\Enum\Dispensa;
use App\Entity\Enum\EstadoAutorizacion;
use App\Entity\SolicitudAutorizacion;

/**
 * Reglas de las autorizaciones (ADR-040), puras:
 *
 * - Qué se puede autorizar: la capacidad declara de qué reglas dispensa
 *   (`Capacidad::dispensas`).
 * - Quién resuelve una solicitud: quien tiene `autorizacion.aprobar` donde
 *   cae la operación y no la pidió él. Si dispensa del permiso, además tiene
 *   que tener esa capacidad ahí (dos personas no hacen juntas lo que ninguna
 *   puede); para el límite o la hora basta con aprobar.
 * - Qué cubre un pase: el mismo solicitante, operación, regla y tipo de
 *   registro, todos los registros pedidos y no más unidades.
 */
final class ReglasAutorizacion
{
    /** @throws AutorizacionRechazada si esa capacidad no dispensa de esa regla */
    public static function exigirAutorizable(Capacidad $capacidad, Dispensa $dispensa): void
    {
        if (!in_array($dispensa, $capacidad->dispensas(), true)) {
            throw new AutorizacionRechazada(
                sprintf("«%s» no se autoriza por excepción (%s).", self::operacion($capacidad), mb_strtolower($dispensa->etiqueta())),
                "no_autorizable",
            );
        }
    }

    /** Por qué `$quien` no puede aprobar ni rechazar la solicitud, o null si puede. */
    public static function rechazoParaResolver(PermisosUsuario $permisos, ?int $quien, SolicitudAutorizacion $s): ?string
    {
        if ($quien !== null && $quien === $s->getSolicitante()->getId()) {
            return "Nadie resuelve sus propias solicitudes.";
        }
        $donde = $s->getUbicacion();
        if (!$permisos->puede(Capacidad::AUTORIZACION_APROBAR->value, $donde)) {
            return "No aprueba autorizaciones de esa empresa o estación.";
        }
        if ($s->getDispensa() === Dispensa::PERMISO && !$permisos->puede($s->getCapacidad(), $donde)) {
            return sprintf("Para autorizar «%s» sin permiso tiene que poder hacerlo usted ahí.", self::operacion($s->getCapacidad()));
        }

        return null;
    }

    /**
     * Qué tan cerca de la operación aprueba `$permisos`, para avisar primero
     * a los más cercanos: 0 = un alcance acotado a estaciones que incluye la
     * de la operación, 1 = acotado a empresas, 2 = sobre todas. Null si no
     * aprueba ahí.
     */
    public static function cercania(PermisosUsuario $permisos, SolicitudAutorizacion $s): ?int
    {
        return self::cercaniaA($permisos, $s->getUbicacion());
    }

    /** Lo mismo para una operación cualquiera en `$donde` (los avisos de umbral, ADR-044). */
    public static function cercaniaA(PermisosUsuario $permisos, Ubicacion $donde): ?int
    {
        $mejor = null;
        foreach ($permisos->alcance(Capacidad::AUTORIZACION_APROBAR->value)->ambitos() as $ambito) {
            if ($ambito->contiene($donde)) {
                $nivel = $ambito->estaciones !== null ? 0 : ($ambito->empresas !== null ? 1 : 2);
                $mejor = $mejor === null ? $nivel : min($mejor, $nivel);
            }
        }

        return $mejor;
    }

    /** ¿El pase aprobado `$s` cubre la operación `$a` de `$usuario` a esa hora? */
    public static function cubre(SolicitudAutorizacion $s, int $usuario, Autorizable $a, \DateTimeImmutable $ahora): bool
    {
        return $s->getSolicitante()->getId() === $usuario
            && $s->estadoEn($ahora) === EstadoAutorizacion::APROBADA
            && $s->getCapacidad() === $a->capacidad->value
            && $s->getDispensa() === $a->dispensa
            && $s->getSujeto() === $a->sujeto
            && array_diff($a->ids, $s->getSujetoIds()) === []
            && $a->cantidad <= $s->getCantidad();
    }

    /** ¿Piden lo mismo (y alguno de los mismos registros)? Para no duplicar solicitudes abiertas. */
    public static function seSuperponen(SolicitudAutorizacion $s, Autorizable $a): bool
    {
        return $s->getCapacidad() === $a->capacidad->value
            && $s->getDispensa() === $a->dispensa
            && $s->getSujeto() === $a->sujeto
            && array_intersect($a->ids, $s->getSujetoIds()) !== [];
    }

    /** «anular boletos», «reasignar boletos», «emitir cortesías». */
    public static function operacion(Capacidad|string $capacidad): string
    {
        $c = $capacidad instanceof Capacidad ? $capacidad : Capacidad::tryFrom($capacidad);

        return match ($c) {
            Capacidad::BOLETO_ANULAR => "anular boletos",
            Capacidad::BOLETO_REASIGNAR => "reasignar boletos",
            Capacidad::VENTA_CORTESIA => "emitir cortesías",
            null => (string) $capacidad,
            default => lcfirst($c->nombre()),
        };
    }
}
