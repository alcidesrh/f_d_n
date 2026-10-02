<?php

declare(strict_types=1);

namespace App\Salida\Programacion;

use App\Salida\SalidaRechazada;

/** Un momento del día (hora y minutos) en que sale un bus, dentro de una programación o un esquema. */
final class Momento
{
    private function __construct(
        /** `HH:MM`, 24 h */
        public readonly string $hora,
        public readonly int $busId,
    ) {}

    public static function de(mixed $hora, mixed $busId): self
    {
        $hora = is_string($hora) ? trim($hora) : "";
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $m) !== 1 || (int) $m[1] > 23 || (int) $m[2] > 59) {
            throw new SalidaRechazada("La hora «{$hora}» no es válida (use HH:MM, 24 horas).", "hora_invalida");
        }
        if (!is_numeric($busId) || (int) $busId <= 0) {
            throw new SalidaRechazada("Elija el bus de la salida de las {$hora}.", "bus_requerido");
        }

        return new self(sprintf("%02d:%s", (int) $m[1], $m[2]), (int) $busId);
    }

    /**
     * Lista de `{ hora, busId }` validada y ordenada por hora. Rechaza la
     * lista vacía, la demasiado larga y el mismo bus dos veces a la misma hora.
     *
     * @return list<self>
     */
    public static function lista(mixed $datos, int $maximo): array
    {
        if (!is_array($datos) || $datos === []) {
            throw new SalidaRechazada("Agregue al menos una hora de salida.", "sin_momentos");
        }
        if (count($datos) > $maximo) {
            throw new SalidaRechazada("No se pueden programar más de {$maximo} horas por día.", "demasiados_momentos");
        }
        $momentos = [];
        foreach ($datos as $d) {
            $m = self::de($d["hora"] ?? null, $d["busId"] ?? null);
            $clave = "{$m->hora}|{$m->busId}";
            if (isset($momentos[$clave])) {
                throw new SalidaRechazada("El mismo bus está dos veces a las {$m->hora}.", "momento_repetido");
            }
            $momentos[$clave] = $m;
        }
        $momentos = array_values($momentos);
        usort($momentos, static fn(self $a, self $b) => [$a->hora, $a->busId] <=> [$b->hora, $b->busId]);

        return $momentos;
    }

    /** @return array{hora: string, busId: int} */
    public function toArray(): array
    {
        return ["hora" => $this->hora, "busId" => $this->busId];
    }
}
