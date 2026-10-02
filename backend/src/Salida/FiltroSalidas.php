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

    /**
     * @param list<EstadoSalida> $estados vacío = todos
     */
    public function __construct(
        public readonly array $estados,
        public readonly ?int $empresa = null,
        public readonly ?int $trayecto = null,
        public readonly ?int $origen = null,
        public readonly ?int $destino = null,
        public readonly ?int $bus = null,
        public readonly ?\DateTimeImmutable $desde = null,
        /** inclusive (todo el día) */
        public readonly ?\DateTimeImmutable $hasta = null,
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
     * `empresa`, `trayecto`, `origen`, `destino`, `bus`, `desde`/`hasta`
     * (AAAA-MM-DD), `orden` (proximas|fecha), `direccion`, `pagina`, `porPagina`.
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
            trayecto: self::id($q["trayecto"] ?? null),
            origen: self::id($q["origen"] ?? null),
            destino: self::id($q["destino"] ?? null),
            bus: self::id($q["bus"] ?? null),
            desde: self::dia($q["desde"] ?? null),
            hasta: self::dia($q["hasta"] ?? null),
            orden: ($q["orden"] ?? null) === self::FECHA ? self::FECHA : self::PROXIMAS,
            direccion: ($q["direccion"] ?? null) === "desc" ? "desc" : "asc",
            pagina: max(1, (int) ($q["pagina"] ?? 1)),
            porPagina: in_array($porPagina, self::POR_PAGINA, true) ? $porPagina : 25,
        );
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
        $d = \DateTimeImmutable::createFromFormat("!Y-m-d", $v);

        return $d !== false && $d->format("Y-m-d") === $v ? $d : null;
    }
}
