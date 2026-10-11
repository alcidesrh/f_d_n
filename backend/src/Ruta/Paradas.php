<?php

declare(strict_types=1);

namespace App\Ruta;

/**
 * Paradas de una ruta tal como las edita el usuario (puro, ADR-030):
 * validación y la ruta de regreso.
 *
 * Cada parada: `{ enclaveId, minutos?, km?, permiteSubir?, permiteBajar?,
 * tiempos?: [{ claseId, minutos }] }`, en orden. Los minutos son desde el
 * origen: la primera no tiene; en las demás, si se dan, no retroceden.
 *
 * @phpstan-type ParadaEditada array{enclaveId: int, minutos: ?int, km: ?int, permiteSubir: bool, permiteBajar: bool, tiempos: array<int, int>}
 */
final class Paradas
{
    public const MAX = 60;

    /**
     * @return list<ParadaEditada>
     */
    public static function desdeArray(mixed $datos): array
    {
        if (!is_array($datos) || count($datos) < 2) {
            throw new RutaRechazada("Una ruta necesita al menos dos paradas: el origen y el destino.", "paradas_insuficientes");
        }
        if (count($datos) > self::MAX) {
            throw new RutaRechazada("Una ruta no puede tener más de " . self::MAX . " paradas.", "demasiadas_paradas");
        }
        $paradas = [];
        $vistos = [];
        foreach (array_values($datos) as $i => $p) {
            $enclave = (int) ($p["enclaveId"] ?? 0);
            if ($enclave <= 0) {
                throw new RutaRechazada("Elija el lugar de la parada " . ($i + 1) . ".", "parada_sin_lugar");
            }
            if (isset($vistos[$enclave])) {
                throw new RutaRechazada("Un lugar aparece dos veces en la ruta (paradas " . ($vistos[$enclave] + 1) . " y " . ($i + 1) . ").", "parada_repetida");
            }
            $vistos[$enclave] = $i;
            $tiempos = [];
            foreach ((array) ($p["tiempos"] ?? []) as $t) {
                $clase = (int) ($t["claseId"] ?? 0);
                $m = self::entero($t["minutos"] ?? null, $i);
                if ($clase > 0 && $m !== null && $i > 0) {
                    $tiempos[$clase] = $m;
                }
            }
            $paradas[] = [
                "enclaveId" => $enclave,
                "minutos" => $i === 0 ? null : self::entero($p["minutos"] ?? null, $i),
                "km" => self::entero($p["km"] ?? null, $i),
                "permiteSubir" => (bool) ($p["permiteSubir"] ?? true),
                "permiteBajar" => (bool) ($p["permiteBajar"] ?? true),
                "tiempos" => $tiempos,
            ];
        }
        return $paradas;
    }

    /**
     * La ruta de regreso: las paradas al revés; los minutos y km se cuentan
     * desde el nuevo origen (el total menos el de ida), si se conoce el total.
     *
     * @param list<ParadaEditada> $paradas
     *
     * @return list<ParadaEditada>
     */
    public static function invertir(array $paradas): array
    {
        $n = count($paradas);
        $ultima = $paradas[$n - 1];
        // Valor de la ida en la parada original `o` (el origen vale 0).
        $ida = static fn(int $o, string $campo) => $o === 0 ? 0 : $paradas[$o][$campo];
        $idaClase = static fn(int $o, int $clase) => $o === 0 ? 0 : ($paradas[$o]["tiempos"][$clase] ?? $paradas[$o]["minutos"]);
        $resta = static fn(?int $total, ?int $valor) => $total !== null && $valor !== null ? $total - $valor : null;
        $clases = array_keys($ultima["tiempos"]);

        $vuelta = [];
        for ($i = 0; $i < $n; $i++) {
            $o = $n - 1 - $i;
            $minutos = $i === 0 ? null : $resta($ultima["minutos"], $ida($o, "minutos"));
            $tiempos = [];
            foreach ($i === 0 ? [] : $clases as $clase) {
                $m = $resta($ultima["tiempos"][$clase], $idaClase($o, $clase));
                if ($m !== null && $m !== $minutos) {
                    $tiempos[$clase] = $m;
                }
            }
            $vuelta[] = [
                "enclaveId" => $paradas[$o]["enclaveId"],
                "minutos" => $minutos,
                "km" => $i === 0 ? null : $resta($ultima["km"], $ida($o, "km")),
                "permiteSubir" => $paradas[$o]["permiteBajar"],
                "permiteBajar" => $paradas[$o]["permiteSubir"],
                "tiempos" => $tiempos,
            ];
        }

        return $vuelta;
    }

    /**
     * Paradas cuyos minutos son menos que los de una anterior.
     *
     * @param list<array{minutos: ?int}> $paradas
     *
     * @return array<int, string> por posición de la parada
     */
    public static function avisos(array $paradas): array
    {
        $avisos = [];
        $anterior = 0;
        foreach ($paradas as $i => $p) {
            if ($p["minutos"] === null) {
                continue;
            }
            if ($p["minutos"] < $anterior) {
                $avisos[$i] = sprintf("%d min es menos que una parada anterior (%d min): revise el orden o los minutos.", $p["minutos"], $anterior);
            } else {
                $anterior = $p["minutos"];
            }
        }

        return $avisos;
    }

    private static function entero(mixed $v, int $i): ?int
    {
        if ($v === null || $v === "") {
            return null;
        }
        if (!is_numeric($v) || (int) $v < 0 || (int) $v > 100000) {
            throw new RutaRechazada("Valor inválido en la parada " . ($i + 1) . ".", "valor_invalido");
        }

        return (int) $v;
    }
}
