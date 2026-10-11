<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Enum\PrecisionHora;

/**
 * Paradas de una salida en orden (su horario, `SalidaParada`, ADR-030), los
 * trayectos que se pueden vender dentro de ella (cada par de paradas en
 * orden que tiene `Trayecto`) y la hora en cada parada. Es puro: se
 * construye con ids (ver `Itinerarios` para leerlo de la base).
 */
final class Itinerario
{
    /**
     * @param list<int>                                                       $paradas   ids de enclave en orden (posición = `orden`)
     * @param array<int, array{origen: int, destino: int}>                    $trayectos trayectos vendibles por id
     * @param array<int, array{hora: \DateTimeImmutable, precision: PrecisionHora}> $horas  por id de enclave
     */
    private function __construct(
        /** Trayecto entre la primera y la última parada (el "completo"), si existe. */
        public readonly ?int $trayectoId,
        public readonly array $paradas,
        private readonly array $trayectos,
        private readonly array $horas,
    ) {}

    /**
     * @param list<int>                                                       $paradas    ids de enclave en orden
     * @param array<int, array{origen: int, destino: int}>                    $trayectos  pares con trayecto, por id
     * @param array<int, array{hora: \DateTimeImmutable, precision: PrecisionHora}> $horas por id de enclave
     */
    public static function deParadas(array $paradas, array $trayectos, array $horas = []): self
    {
        $paradas = array_values($paradas);
        $posicion = array_flip($paradas);
        $validos = array_filter(
            $trayectos,
            static fn(array $t) => isset($posicion[$t["origen"]], $posicion[$t["destino"]])
                && $posicion[$t["origen"]] < $posicion[$t["destino"]],
        );
        $completo = null;
        foreach ($validos as $id => $t) {
            if ($t["origen"] === ($paradas[0] ?? null) && $t["destino"] === ($paradas[count($paradas) - 1] ?? null)) {
                $completo = $id;
            }
        }

        return new self($completo, $paradas, $validos, $horas);
    }

    public function contieneTrayecto(int $trayectoId): bool
    {
        return isset($this->trayectos[$trayectoId]);
    }

    /** Tramo de un trayecto vendible; null si no es de este itinerario. */
    public function tramo(int $trayectoId): ?Tramo
    {
        $t = $this->trayectos[$trayectoId] ?? null;

        return $t === null ? null : $this->tramoEntre($t["origen"], $t["destino"]);
    }

    /** Tramo entre dos paradas; null si alguna no está o van hacia atrás. */
    public function tramoEntre(int $origenId, int $destinoId): ?Tramo
    {
        $o = array_search($origenId, $this->paradas, true);
        $d = array_search($destinoId, $this->paradas, true);

        return $o !== false && $d !== false && $o < $d ? new Tramo($o, $d) : null;
    }

    /** El itinerario completo. */
    public function completo(): Tramo
    {
        return new Tramo(0, count($this->paradas) - 1);
    }

    /** Id del trayecto vendible entre dos paradas, si existe. */
    public function trayectoEntre(int $origenId, int $destinoId): ?int
    {
        foreach ($this->trayectos as $id => $t) {
            if ($t["origen"] === $origenId && $t["destino"] === $destinoId) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{origen: int, destino: int}> trayectos vendibles por id
     */
    public function trayectos(): array
    {
        return $this->trayectos;
    }

    /**
     * Hora de la salida en una parada y su precisión; null si no es parada.
     *
     * @return array{hora: \DateTimeImmutable, precision: PrecisionHora}|null
     */
    public function hora(int $enclaveId): ?array
    {
        return $this->horas[$enclaveId] ?? null;
    }

    /** Posición de una parada (0 = origen); null si no está. */
    public function posicion(int $enclaveId): ?int
    {
        $p = array_search($enclaveId, $this->paradas, true);

        return $p === false ? null : $p;
    }
}
