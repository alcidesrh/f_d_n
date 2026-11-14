<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Enum\EstadoSalida;

/**
 * Filtro del listado de salidas (`GET /api/gestion-salidas`). Por defecto
 * omite las finalizadas y las canceladas, y ordena por "próximas": primero
 * las iniciadas sin terminar, luego las que están abordando y después las
 * programadas de la más próxima a la más lejana. Puro.
 */
final class FiltroSalidas
{
    public const PROXIMAS = "proximas";
    public const FECHA = "fecha";
    public const POR_PAGINA = [10, 25, 50, 100];
    /** A lo sumo tantos días con `hora`: más que eso, la hora se ignora. */
    public const MAX_DIAS_HORA = 62;

    /**
     * @param list<EstadoSalida> $estados vacío = todos
     */
    public function __construct(
        public readonly array $estados,
        public readonly ?int $empresa = null,
        public readonly ?int $ruta = null,
        public readonly ?int $origen = null,
        public readonly ?int $destino = null,
        public readonly ?int $bus = null,
        public readonly ?\DateTimeImmutable $desde = null,
        /** inclusive: a las 00:00, todo el día; con hora, hasta ese minuto */
        public readonly ?\DateTimeImmutable $hasta = null,
        /** `HH:MM`: solo las salidas a esa hora exacta, en cada día de `desde`–`hasta` */
        public readonly ?string $hora = null,
        public readonly string $orden = self::PROXIMAS,
        public readonly string $direccion = "asc",
        public readonly int $pagina = 1,
        public readonly int $porPagina = 25,
    ) {}

    /** @return list<EstadoSalida> */
    public static function estadosPorDefecto(): array
    {
        return [EstadoSalida::INICIADA, EstadoSalida::ABORDANDO, EstadoSalida::PROGRAMADA];
    }

    /**
     * `estado` (lista separada por comas; vacío = todos; ausente = por defecto),
     * `empresa`, `ruta`, `origen`, `destino` (puntas de la ruta), `bus`, `desde`/`hasta`
     * (AAAA-MM-DD o AAAA-MM-DDTHH:MM), `hora` (HH:MM), `orden` (proximas|fecha), `direccion`, `pagina`, `porPagina`.
     * Los valores inválidos se ignoran.
     *
     * @param array<string, mixed> $q
     */
    public static function desdeQuery(array $q): self
    {
        $estados = self::estadosPorDefecto();
        if (array_key_exists("estado", $q)) {
            $estados = array_values(array_filter(array_map(
                static fn(string $e) => EstadoSalida::tryFrom(trim($e)),
                explode(",", (string) $q["estado"]),
            )));
        }
        $porPagina = (int) ($q["porPagina"] ?? 25);

        return new self(
            estados: $estados,
            empresa: self::id($q["empresa"] ?? null),
            ruta: self::id($q["ruta"] ?? null),
            origen: self::id($q["origen"] ?? null),
            destino: self::id($q["destino"] ?? null),
            bus: self::id($q["bus"] ?? null),
            desde: self::dia($q["desde"] ?? null),
            hasta: self::dia($q["hasta"] ?? null),
            hora: is_string($q["hora"] ?? null) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $q["hora"]) === 1 ? $q["hora"] : null,
            orden: ($q["orden"] ?? null) === self::FECHA ? self::FECHA : self::PROXIMAS,
            direccion: ($q["direccion"] ?? null) === "desc" ? "desc" : "asc",
            pagina: max(1, (int) ($q["pagina"] ?? 1)),
            porPagina: in_array($porPagina, self::POR_PAGINA, true) ? $porPagina : 25,
        );
    }

    /** Límite superior excluyente de `hasta`: el día siguiente si no trae hora, si no el minuto siguiente. */
    public function finExclusivo(): ?\DateTimeImmutable
    {
        return $this->hasta?->modify($this->hasta->format("H:i") === "00:00" ? "+1 day" : "+1 minute");
    }

    /**
     * Con `hora`, `desde` y `hasta`: el minuto de esa hora en cada día del rango,
     * como [inicio, fin exclusivo). Sin alguno de los tres (o con un rango de
     * más de `MAX_DIAS_HORA` días), ninguno: la hora no filtra.
     *
     * @return list<array{\DateTimeImmutable, \DateTimeImmutable}>
     */
    public function franjasDeHora(): array
    {
        if ($this->hora === null || $this->desde === null || $this->hasta === null) {
            return [];
        }
        [$h, $m] = array_map(intval(...), explode(":", $this->hora));
        $dia = $this->desde->setTime(0, 0);
        $ultimo = $this->hasta->setTime(0, 0);
        $franjas = [];
        while ($dia <= $ultimo && count($franjas) < self::MAX_DIAS_HORA) {
            $inicio = $dia->setTime($h, $m);
            $franjas[] = [$inicio, $inicio->modify("+1 minute")];
            $dia = $dia->modify("+1 day");
        }

        return $dia <= $ultimo ? [] : $franjas;
    }

    private static function id(mixed $v): ?int
    {
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    private static function dia(mixed $v): ?\DateTimeImmutable
    {
        if (!is_string($v)) {
            return null;
        }
        foreach (["Y-m-d", "Y-m-d\\TH:i"] as $formato) {
            $d = \DateTimeImmutable::createFromFormat("!" . $formato, $v);
            if ($d !== false && $d->format($formato) === $v) {
                return $d;
            }
        }

        return null;
    }
}
