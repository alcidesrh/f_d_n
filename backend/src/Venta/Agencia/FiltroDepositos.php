<?php

declare(strict_types=1);

namespace App\Venta\Agencia;

use App\Http\Parametros;
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
        foreach (array_filter(explode(",", (string) Parametros::texto($q["estado"] ?? null, 50))) as $e) {
            $estados[] = EstadoDepositoAgencia::tryFrom(trim($e)) ?? throw new VentaRechazada("El estado no es válido.", "parametro_invalido", 400);
        }
        $porPagina = Parametros::entero($q["porPagina"] ?? null, "el tamaño de página") ?? 15;

        return new self(
            Parametros::dia($q["desde"] ?? null, "la fecha inicial") ?? $hoy->modify("-1 month"),
            Parametros::dia($q["hasta"] ?? null, "la fecha final") ?? $hoy->modify("+1 month"),
            id: Parametros::entero($q["id"] ?? null, "el id"),
            agencia: Parametros::entero($q["agencia"] ?? null, "la agencia"),
            boleta: Parametros::texto($q["boleta"] ?? null, 50),
            estados: array_values(array_unique($estados, SORT_REGULAR)),
            pagina: max(1, Parametros::entero($q["pagina"] ?? null, "la página") ?? 1),
            porPagina: in_array($porPagina, self::POR_PAGINA, true) ? $porPagina : 15,
        );
    }
}
