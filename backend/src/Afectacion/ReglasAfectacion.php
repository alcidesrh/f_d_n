<?php

declare(strict_types=1);

namespace App\Afectacion;

use App\Acceso\Capacidad;
use App\Acceso\Dimension;
use App\Acceso\PermisosUsuario;
use App\Acceso\Ubicacion;
use App\Entity\Afectacion;
use App\Entity\TipoAfectacion;

/**
 * Reglas puras de las afectaciones (ADR-070): dónde cae una para cada
 * capacidad, quién la ve y quién la atiende, y los textos de los avisos.
 *
 * Las capacidades del tipo (`owner`, `target`, `escalamiento`) son códigos
 * del catálogo; cada una se acota por sus propias dimensiones (empresa,
 * estación o ninguna), así que la misma afectación se ubica distinto para
 * cada una: `afectacion.flota` mira solo la empresa del bus,
 * `afectacion.informatica` la empresa y la estación.
 */
final class ReglasAfectacion
{
    /**
     * @param list<string> $dimensiones de la capacidad (`empresa`, `estacion`)
     */
    public static function ubicacion(array $dimensiones, ?int $empresa, ?int $estacion): Ubicacion
    {
        $u = in_array(Dimension::EMPRESA->value, $dimensiones, true) ? Ubicacion::empresa($empresa) : Ubicacion::ninguna();

        return in_array(Dimension::ESTACION->value, $dimensiones, true) ? $u->conEstacion($estacion) : $u;
    }

    /**
     * Tiene alguna de las capacidades donde cae la afectación.
     *
     * @param list<string>                $capacidades
     * @param array<string, list<string>> $dimensiones capacidad → sus dimensiones
     */
    public static function alcanza(PermisosUsuario $permisos, array $capacidades, array $dimensiones, ?int $empresa, ?int $estacion): bool
    {
        foreach ($capacidades as $c) {
            if ($permisos->puede($c, self::ubicacion($dimensiones[$c] ?? [], $empresa, $estacion))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Quiénes la ven además de quien la reportó: quienes la pueden reportar
     * (así no la repiten), quien la atiende, a quienes se escala y quien
     * supervisa las afectaciones.
     *
     * @return list<string>
     */
    public static function capacidadesQueVen(TipoAfectacion $t): array
    {
        return array_values(array_unique([$t->getOwner(), $t->getTarget(), ...$t->getEscalamiento(), Capacidad::AFECTACION_SUPERVISAR->value]));
    }

    /** Dónde cae: «Estación Central», «bus 120 (Estación Central)», «Transportes X». */
    public static function lugar(Afectacion $a): string
    {
        $estacion = $a->getEstacion()?->getNombre();
        $bus = $a->getBus();
        if ($bus !== null) {
            return "bus " . $bus->getCodigo() . ($estacion ? " ({$estacion})" : "");
        }

        return $estacion ?? $a->getEmpresa()?->getNombre() ?? "sin lugar";
    }

    /** «45 min», «3 h», «2 días» (hacia abajo). */
    public static function lapso(int $minutos): string
    {
        $minutos = max(0, $minutos);

        return match (true) {
            $minutos < 60 => "{$minutos} min",
            $minutos < 48 * 60 => intdiv($minutos, 60) . " h",
            default => intdiv($minutos, 24 * 60) . " días",
        };
    }

    /** El texto del aviso del centinela por el canal del sistema del chat. */
    public static function aviso(Afectacion $a, bool $escalada, \DateTimeImmutable $ahora): string
    {
        $t = $a->getTipo();
        $cabeza = sprintf("Afectación #%d · %s: %s en %s.", $a->getId(), $t->getPrioridad()->etiqueta(), $t->getNombre(), self::lugar($a));
        $quien = $a->getReportadaPor();
        $origen = $quien === null ? "La detectó el sistema." : sprintf("La reportó %s.", trim((string) $quien->getFullName()) ?: $quien->getUsername());
        $lineas = [$cabeza, "«" . $a->getDescripcion() . "»", $origen];
        if ($escalada) {
            $minutos = intdiv(max(0, $ahora->getTimestamp() - $a->getCreadaEn()->getTimestamp()), 60);
            array_unshift($lineas, sprintf("Sin resolver desde hace %s (aviso n.º %d).", self::lapso($minutos), $a->getAvisos() + 1));
        }

        return implode("\n", $lineas);
    }

    /** A quien la reportó, cuando se resuelve. */
    public static function avisoResuelta(Afectacion $a): string
    {
        $quien = $a->getResueltaPor();
        $nombre = $quien === null ? "el sistema" : (trim((string) $quien->getFullName()) ?: $quien->getUsername());

        return sprintf(
            "Se resolvió su afectación #%d (%s en %s). La resolvió %s.%s",
            $a->getId(),
            $a->getTipo()->getNombre(),
            self::lugar($a),
            $nombre,
            $a->getResolucion() ? " «" . $a->getResolucion() . "»" : "",
        );
    }
}
