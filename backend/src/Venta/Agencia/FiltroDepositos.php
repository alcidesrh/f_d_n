<?php

declare(strict_types=1);

namespace App\Venta\Agencia;

use App\Entity\Enum\EstadoDepositoAgencia;
use App\Venta\Excepcion\VentaRechazada;

/**
 * Filtros de "Buscar depósito" (ADR-034): rango por la fecha del depósito
 * (por defecto un mes antes y uno después de hoy, como el legado; no aplica
 * si se pide un id), agencia, número de boleta (contiene) y estados.
 */
final readonly class FiltroDepositos
{
    public const MAX_DIAS = 366;
    public const POR_PAGINA = [10, 15, 25, 50, 100];

    /**
     * @param list<EstadoDepositoAgencia> $estados
     */
    public function __construct(
        public \DateTimeImmutable $desde,
        public \DateTimeImmutable $hasta,
        public ?int $id = null,
        public ?int $agencia = null,
        public ?string $boleta = null,
        public array $estados = [],
        public int $pagina = 1,
        public int $porPagina = 15,
    ) {
        if ($hasta < $desde) {
            throw new VentaRechazada("La fecha final no puede ser anterior a la inicial.", "rango_invalido", 400);
        }
        if ($desde->diff($hasta)->days > self::MAX_DIAS) {
            throw new VentaRechazada("El rango de fechas no puede pasar de un año.", "rango_invalido", 400);
        }
    }

    /** @param array<string, mixed> $q */
    public static function desdeQuery(array $q, \DateTimeImmutable $hoy): self
    {
        $hoy = $hoy->setTime(0, 0);
        $estados = [];
        foreach (array_filter(explode(",", (string) self::texto($q["estado"] ?? null))) as $e) {
            $estados[] = EstadoDepositoAgencia::tryFrom(trim($e)) ?? throw new VentaRechazada("El estado no es válido.", "parametro_invalido", 400);
        }
        $porPagina = self::entero($q["porPagina"] ?? null, "el tamaño de página") ?? 15;

        return new self(
            self::dia($q["desde"] ?? null, "la fecha inicial") ?? $hoy->modify("-1 month"),
            self::dia($q["hasta"] ?? null, "la fecha final") ?? $hoy->modify("+1 month"),
            id: self::entero($q["id"] ?? null, "el id"),
            agencia: self::entero($q["agencia"] ?? null, "la agencia"),
            boleta: self::texto($q["boleta"] ?? null),
            estados: array_values(array_unique($estados, SORT_REGULAR)),
            pagina: max(1, self::entero($q["pagina"] ?? null, "la página") ?? 1),
            porPagina: in_array($porPagina, self::POR_PAGINA, true) ? $porPagina : 15,
        );
    }

    private static function dia(mixed $v, string $campo): ?\DateTimeImmutable
    {
        if ($v === null || $v === "") {
            return null;
        }
        $d = is_string($v) ? \DateTimeImmutable::createFromFormat("!Y-m-d", $v) : false;
        if ($d === false || $d->format("Y-m-d") !== $v) {
            throw new VentaRechazada(sprintf("Indica %s con el formato AAAA-MM-DD.", $campo), "fecha_invalida", 400);
        }

        return $d;
    }

    private static function entero(mixed $v, string $campo): ?int
    {
        if ($v === null || $v === "") {
            return null;
        }
        $t = is_scalar($v) ? trim((string) $v) : "";
        if (!ctype_digit($t) || strlen($t) > 9) {
            throw new VentaRechazada(sprintf("%s no es válido.", ucfirst($campo)), "parametro_invalido", 400);
        }

        return (int) $t;
    }

    private static function texto(mixed $v): ?string
    {
        $t = is_string($v) ? trim($v) : "";

        return $t === "" ? null : mb_substr($t, 0, 50);
    }
}
