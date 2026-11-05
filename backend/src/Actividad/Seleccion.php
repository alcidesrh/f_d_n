<?php

declare(strict_types=1);

namespace App\Actividad;

/**
 * Los usuarios a los que se les sigue la actividad cuando el alcance es
 * "solo seleccionados": por usuario, rol, empresa o estación, cada uno con
 * fechas opcionales (una revisión que se apaga sola). Puro.
 *
 * Cada entrada es `{ tipo: usuario|rol|empresa|estacion, id, desde?: Y-m-d, hasta?: Y-m-d }`;
 * `hasta` incluye ese día.
 */
final class Seleccion
{
    public const TIPOS = ["usuario", "rol", "empresa", "estacion"];

    /** @var list<array{tipo: string, id: int, desde: ?string, hasta: ?string}> */
    private array $entradas;

    /** @param list<array<string, mixed>> $entradas */
    public function __construct(array $entradas)
    {
        $this->entradas = self::normalizar($entradas);
    }

    /** @return list<array{tipo: string, id: int, desde: ?string, hasta: ?string}> */
    public function entradas(): array
    {
        return $this->entradas;
    }

    public function vacia(): bool
    {
        return $this->entradas === [];
    }

    public function incluye(Sujeto $s, \DateTimeInterface $ahora): bool
    {
        $hoy = $ahora->format("Y-m-d");
        foreach ($this->entradas as $e) {
            if (($e["desde"] !== null && $hoy < $e["desde"]) || ($e["hasta"] !== null && $hoy > $e["hasta"])) {
                continue;
            }
            $coincide = match ($e["tipo"]) {
                "usuario" => $e["id"] === $s->usuario,
                "rol" => in_array($e["id"], $s->roles, true),
                // Alcance sin restricción de empresa: está en todas.
                "empresa" => $s->empresas === null || in_array($e["id"], $s->empresas, true),
                "estacion" => $e["id"] === $s->estacion,
                default => false,
            };
            if ($coincide) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida y ordena lo que llega de la administración.
     *
     * @param list<array<string, mixed>>|mixed $entradas
     *
     * @return list<array{tipo: string, id: int, desde: ?string, hasta: ?string}>
     *
     * @throws \InvalidArgumentException con el motivo
     */
    public static function validar(mixed $entradas): array
    {
        if (!is_array($entradas) || !array_is_list($entradas)) {
            throw new \InvalidArgumentException("La selección debe ser una lista.");
        }
        foreach ($entradas as $e) {
            if (!is_array($e) || !in_array($e["tipo"] ?? null, self::TIPOS, true)) {
                throw new \InvalidArgumentException("Cada entrada de la selección necesita un tipo: usuario, rol, empresa o estación.");
            }
            if (!is_int($e["id"] ?? null) && !(is_string($e["id"] ?? null) && ctype_digit($e["id"]))) {
                throw new \InvalidArgumentException("Cada entrada de la selección necesita el id de lo elegido.");
            }
            foreach (["desde", "hasta"] as $campo) {
                $v = $e[$campo] ?? null;
                if ($v !== null && $v !== "" && \DateTimeImmutable::createFromFormat("!Y-m-d", (string) $v) === false) {
                    throw new \InvalidArgumentException(sprintf("La fecha «%s» de la selección no es válida (AAAA-MM-DD).", (string) $v));
                }
            }
            if (($e["desde"] ?? null) && ($e["hasta"] ?? null) && $e["desde"] > $e["hasta"]) {
                throw new \InvalidArgumentException("En la selección, «desde» no puede ser después de «hasta».");
            }
        }

        return self::normalizar($entradas);
    }

    /**
     * @param list<array<string, mixed>> $entradas
     *
     * @return list<array{tipo: string, id: int, desde: ?string, hasta: ?string}>
     */
    private static function normalizar(array $entradas): array
    {
        $unicas = [];
        foreach ($entradas as $e) {
            if (!is_array($e) || !in_array($e["tipo"] ?? null, self::TIPOS, true) || (int) ($e["id"] ?? 0) <= 0) {
                continue;
            }
            $n = [
                "tipo" => (string) $e["tipo"],
                "id" => (int) $e["id"],
                "desde" => ($e["desde"] ?? null) ?: null,
                "hasta" => ($e["hasta"] ?? null) ?: null,
            ];
            $unicas[implode("|", [$n["tipo"], $n["id"], $n["desde"], $n["hasta"]])] = $n;
        }
        $lista = array_values($unicas);
        usort($lista, static fn(array $a, array $b) => [array_search($a["tipo"], self::TIPOS, true), $a["id"]] <=> [array_search($b["tipo"], self::TIPOS, true), $b["id"]]);

        return $lista;
    }
}
