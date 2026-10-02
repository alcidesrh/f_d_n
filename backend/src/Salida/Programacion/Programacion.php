<?php

declare(strict_types=1);

namespace App\Salida\Programacion;

use App\Salida\SalidaRechazada;

/**
 * Lo que pide el programador de salidas: un trayecto, las horas del día
 * (cada una con su bus) y los días en que se repite —desde un día hasta
 * otro, cada `intervaloDias` días (1 = diario, 2 = día por medio, …)—.
 * Puro: valida y despliega las fechas; no sabe de la base de datos.
 */
final class Programacion
{
    public const MAX_MOMENTOS = 48;
    public const MAX_INTERVALO = 60;
    /** El rango más largo que se puede programar de una vez. */
    public const MAX_DIAS = 366;
    /** Tope de salidas por programación (evita crear miles por error). */
    public const MAX_SALIDAS = 3000;

    /**
     * @param list<Momento> $momentos ordenados por hora
     */
    private function __construct(
        public readonly int $trayectoId,
        public readonly array $momentos,
        public readonly \DateTimeImmutable $desde,
        public readonly \DateTimeImmutable $hasta,
        public readonly int $intervaloDias,
    ) {}

    /**
     * `{ trayectoId, momentos: [{ hora, busId }], desde: AAAA-MM-DD, hasta?: AAAA-MM-DD, intervaloDias?: n }`.
     * Sin `hasta`, solo el día `desde`. `$hoy` es el primer día permitido.
     *
     * @param array<string, mixed> $datos
     */
    public static function desdeArray(array $datos, \DateTimeImmutable $hoy): self
    {
        $trayectoId = (int) ($datos["trayectoId"] ?? 0);
        if ($trayectoId <= 0) {
            throw new SalidaRechazada("Elija el trayecto.", "trayecto_requerido");
        }
        $momentos = Momento::lista($datos["momentos"] ?? null, self::MAX_MOMENTOS);

        $desde = self::dia($datos["desde"] ?? null, "el día de la primera salida");
        $hasta = ($datos["hasta"] ?? null) ? self::dia($datos["hasta"], "la fecha hasta la que se repite") : $desde;
        $hoy = $hoy->setTime(0, 0);
        if ($desde < $hoy) {
            throw new SalidaRechazada("No se pueden programar salidas en días pasados.", "dia_pasado");
        }
        if ($hasta < $desde) {
            throw new SalidaRechazada("La fecha hasta la que se repite es anterior al primer día.", "rango_invalido");
        }
        if ($desde->diff($hasta)->days >= self::MAX_DIAS) {
            throw new SalidaRechazada("Se puede programar como máximo un año de una vez.", "rango_demasiado_largo");
        }

        $intervalo = (int) ($datos["intervaloDias"] ?? 1);
        if ($intervalo < 1 || $intervalo > self::MAX_INTERVALO) {
            throw new SalidaRechazada("El intervalo debe estar entre 1 y " . self::MAX_INTERVALO . " días.", "intervalo_invalido");
        }

        $p = new self($trayectoId, $momentos, $desde, $hasta, $intervalo);
        $total = count($p->dias()) * count($momentos);
        if ($total > self::MAX_SALIDAS) {
            throw new SalidaRechazada(
                "La programación crearía {$total} salidas; el máximo de una vez es " . self::MAX_SALIDAS . ".",
                "demasiadas_salidas",
            );
        }

        return $p;
    }

    /** @return list<\DateTimeImmutable> los días en que hay salidas (a medianoche) */
    public function dias(): array
    {
        $dias = [];
        for ($d = $this->desde; $d <= $this->hasta; $d = $d->modify("+{$this->intervaloDias} days")) {
            $dias[] = $d;
        }

        return $dias;
    }

    /**
     * Cada salida de la programación, en orden de fecha y hora.
     *
     * @return list<array{fecha: \DateTimeImmutable, busId: int}>
     */
    public function salidas(): array
    {
        $salidas = [];
        foreach ($this->dias() as $dia) {
            foreach ($this->momentos as $m) {
                [$h, $i] = explode(":", $m->hora);
                $salidas[] = ["fecha" => $dia->setTime((int) $h, (int) $i), "busId" => $m->busId];
            }
        }

        return $salidas;
    }

    /** @return list<int> */
    public function busIds(): array
    {
        return array_values(array_unique(array_map(static fn(Momento $m) => $m->busId, $this->momentos)));
    }

    private static function dia(mixed $valor, string $que): \DateTimeImmutable
    {
        $d = is_string($valor) ? \DateTimeImmutable::createFromFormat("!Y-m-d", $valor) : false;
        if ($d === false || $d->format("Y-m-d") !== $valor) {
            throw new SalidaRechazada("Indique {$que} (AAAA-MM-DD).", "fecha_invalida");
        }

        return $d;
    }
}
