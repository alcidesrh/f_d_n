<?php

declare(strict_types=1);

namespace App\Venta\EnLinea\Modificacion;

use App\Entity\ConfiguracionPagina;
use App\Venta\Excepcion\VentaRechazada;

/**
 * Reglas de la página para que el cliente modifique sus compras (puras, con
 * la configuración de `ConfiguracionPagina`): cuántas veces se cambia un
 * mismo asiento, hasta cuándo antes de salir y la penalización.
 */
final readonly class PoliticaModificacion
{
    public function __construct(
        public int $modificacionesPorAsiento,
        public int $minutosAntes,
        public Penalizacion $penalizacion,
    ) {}

    public static function de(ConfiguracionPagina $c): self
    {
        return new self(
            $c->getModificacionesPorAsiento(),
            $c->getModificacionMinutos(),
            Penalizacion::de($c->getPenalizacion(), $c->isPenalizacionPorAsiento()),
        );
    }

    /** Hasta cuándo se modifica un viaje que sale (donde sube el pasajero) a `$subida`. */
    public function limite(\DateTimeImmutable $subida): \DateTimeImmutable
    {
        return $subida->modify(sprintf("-%d minutes", $this->minutosAntes));
    }

    /** Por qué no se puede modificar ya un viaje que sale a `$subida`, o null. */
    public function motivoCerrada(\DateTimeImmutable $subida, \DateTimeImmutable $ahora): ?string
    {
        if ($this->modificacionesPorAsiento === 0) {
            return "Las compras en línea no se pueden modificar por el momento.";
        }
        if ($ahora >= $subida) {
            return "El viaje ya salió.";
        }
        if ($ahora >= $this->limite($subida)) {
            return sprintf("Las compras se modifican hasta %d minutos antes de la salida.", $this->minutosAntes);
        }

        return null;
    }

    /** @throws VentaRechazada */
    public function exigirAbierta(\DateTimeImmutable $subida, \DateTimeImmutable $ahora): void
    {
        $motivo = $this->motivoCerrada($subida, $ahora);
        if ($motivo !== null) {
            throw new VentaRechazada($motivo, "modificacion_cerrada", 409);
        }
    }

    /** Cuántas veces más se puede cambiar un asiento que ya se cambió `$hechas` veces. */
    public function restantes(int $hechas): int
    {
        return max(0, $this->modificacionesPorAsiento - $hechas);
    }

    /** @throws VentaRechazada */
    public function exigirRestantes(int $hechas, int|string $asiento): void
    {
        if ($this->restantes($hechas) === 0) {
            throw new VentaRechazada(sprintf(
                "El asiento %s ya se cambió %d %s: es el máximo permitido.",
                $asiento,
                $hechas,
                $hechas === 1 ? "vez" : "veces",
            ), "modificaciones_agotadas", 409);
        }
    }
}
