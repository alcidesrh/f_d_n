<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Paradas de un trayecto en orden y los trayectos (el propio y sus
 * subtrayectos) que se pueden vender dentro de él. Es puro: se construye con
 * ids (ver `Itinerarios` para leerlo de la base).
 *
 * El modelo no guarda el orden de las paradas: se deduce de los
 * subtrayectos, que son aristas origen→destino hacia adelante (el legado
 * genera todos los pares A→B, A→C, B→C, …). El orden es el topológico de
 * esas aristas, con el origen del trayecto primero y el destino al final.
 * Se descartan las aristas que no pueden ser de este sentido (las que llegan
 * al origen o salen del destino, p. ej. el trayecto inverso, que el
 * migrador también cuelga como subtrayecto).
 */
final class Itinerario
{
    /**
     * @param list<int>                                        $paradas   ids de enclave en orden
     * @param array<int, array{origen: int, destino: int}>     $trayectos trayectos vendibles por id
     * @param array<int, int>                                   $minutos   duración estimada por id de trayecto (si se conoce)
     */
    private function __construct(
        public readonly int $trayectoId,
        public readonly array $paradas,
        private readonly array $trayectos,
        private readonly array $minutos = [],
    ) {}

    /**
     * @param list<array{trayecto: int, origen: int, destino: int, minutos?: ?int}> $subtrayectos
     */
    public static function construir(
        int $trayectoId,
        int $origenId,
        int $destinoId,
        array $subtrayectos,
        ?int $minutos = null,
    ): self {
        $aristas = [];
        foreach ($subtrayectos as $sub) {
            [$o, $d] = [$sub["origen"], $sub["destino"]];
            if ($o === $d || $d === $origenId || $o === $destinoId) {
                continue;
            }
            $aristas[] = [$o, $d];
        }

        $paradas = self::ordenar($origenId, $destinoId, $aristas);
        $posicion = array_flip($paradas);

        $trayectos = [$trayectoId => ["origen" => $origenId, "destino" => $destinoId]];
        $duraciones = $minutos !== null ? [$trayectoId => $minutos] : [];
        foreach ($subtrayectos as $sub) {
            $o = $posicion[$sub["origen"]] ?? null;
            $d = $posicion[$sub["destino"]] ?? null;
            if ($o !== null && $d !== null && $o < $d) {
                $trayectos[$sub["trayecto"]] = [
                    "origen" => $sub["origen"],
                    "destino" => $sub["destino"],
                ];
                if (($sub["minutos"] ?? null) !== null) {
                    $duraciones[$sub["trayecto"]] = $sub["minutos"];
                }
            }
        }

        return new self($trayectoId, $paradas, $trayectos, $duraciones);
    }

    /**
     * Orden topológico (Kahn, desempate por id para que sea estable).
     *
     * @param list<array{0: int, 1: int}> $aristas
     *
     * @return list<int>
     */
    private static function ordenar(int $origenId, int $destinoId, array $aristas): array
    {
        $nodos = [];
        $entrantes = [];
        $salientes = [];
        foreach ($aristas as [$o, $d]) {
            $nodos[$o] = $nodos[$d] = true;
            $salientes[$o][$d] = true;
            $entrantes[$d][$o] = true;
        }
        unset($nodos[$origenId], $nodos[$destinoId]);

        $grado = [];
        foreach (array_keys($nodos) as $n) {
            // Solo cuentan las aristas entre paradas intermedias: el origen va
            // siempre primero y el destino siempre al final.
            $grado[$n] = count(array_filter(
                array_keys($entrantes[$n] ?? []),
                static fn(int $o) => isset($nodos[$o]),
            ));
        }

        $orden = [];
        while ($grado !== []) {
            $libres = array_keys(array_filter($grado, static fn(int $g) => $g === 0));
            if ($libres === []) {
                // Ciclo en los datos: se toma el de menor grado para no perder paradas.
                asort($grado);
                $libres = [array_key_first($grado)];
            }
            sort($libres);
            $n = $libres[0];
            $orden[] = $n;
            unset($grado[$n]);
            foreach (array_keys($salientes[$n] ?? []) as $d) {
                if (isset($grado[$d])) {
                    $grado[$d]--;
                }
            }
        }

        return [$origenId, ...$orden, $destinoId];
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
     * Minutos estimados desde la salida del recorrido hasta una parada (la
     * duración del trayecto origen→parada), o null si no se conoce.
     */
    public function minutosHasta(int $enclaveId): ?int
    {
        if ($enclaveId === $this->paradas[0]) {
            return 0;
        }
        $id = $this->trayectoEntre($this->paradas[0], $enclaveId);

        return $id !== null ? ($this->minutos[$id] ?? null) : null;
    }

    /** Posición de una parada (0 = origen); null si no está. */
    public function posicion(int $enclaveId): ?int
    {
        $p = array_search($enclaveId, $this->paradas, true);

        return $p === false ? null : $p;
    }
}
