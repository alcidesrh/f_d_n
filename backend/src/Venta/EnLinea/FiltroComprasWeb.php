<?php

declare(strict_types=1);

namespace App\Venta\EnLinea;

use App\Entity\Enum\EstadoPagoWeb;

/**
 * Filtro del listado de compras de la página (dashboard, ADR-023). Se arma
 * desde la query string; lo que no se entiende se ignora. Por defecto: las
 * compras completadas, las más recientes primero.
 */
final readonly class FiltroComprasWeb
{
    public const ORDENES = ["creado", "monto", "salida"];
    public const MAX_POR_PAGINA = 100;

    /**
     * @param list<string> $estados vacío = todos
     */
    public function __construct(
        public array $estados = [EstadoPagoWeb::COMPLETADO->value],
        public ?string $creadoDesde = null,
        public ?string $creadoHasta = null,
        public ?string $salidaDesde = null,
        public ?string $salidaHasta = null,
        public ?int $empresa = null,
        public ?int $origen = null,
        public ?int $destino = null,
        public ?bool $idaVuelta = null,
        public ?string $facturacion = null,
        public ?string $marca = null,
        /** En centavos. */
        public ?int $montoMinimo = null,
        public ?int $montoMaximo = null,
        public ?string $texto = null,
        public string $orden = "creado",
        public bool $descendente = true,
        public int $pagina = 1,
        public int $porPagina = 25,
    ) {}

    /** @param array<string, mixed> $q */
    public static function desdeQuery(array $q): self
    {
        $estados = [EstadoPagoWeb::COMPLETADO->value];
        if (array_key_exists("estado", $q)) {
            $pedidos = is_array($q["estado"]) ? $q["estado"] : explode(",", (string) $q["estado"]);
            $validos = array_map(static fn(EstadoPagoWeb $e) => $e->value, EstadoPagoWeb::cases());
            $estados = array_values(array_intersect($validos, array_map("strval", $pedidos)));
        }
        $orden = in_array($q["orden"] ?? null, self::ORDENES, true) ? $q["orden"] : "creado";

        return new self(
            estados: $estados,
            creadoDesde: self::dia($q["creadoDesde"] ?? null),
            creadoHasta: self::dia($q["creadoHasta"] ?? null),
            salidaDesde: self::dia($q["salidaDesde"] ?? null),
            salidaHasta: self::dia($q["salidaHasta"] ?? null),
            empresa: self::entero($q["empresa"] ?? null),
            origen: self::entero($q["origen"] ?? null),
            destino: self::entero($q["destino"] ?? null),
            idaVuelta: match ($q["idaVuelta"] ?? null) {
                "si", "1", "true" => true,
                "no", "0", "false" => false,
                default => null,
            },
            facturacion: in_array($q["facturacion"] ?? null, ["certificada", "pendiente", "no_aplica"], true) ? $q["facturacion"] : null,
            marca: in_array($q["marca"] ?? null, ["visa", "mastercard"], true) ? $q["marca"] : null,
            montoMinimo: self::centavos($q["montoMinimo"] ?? null),
            montoMaximo: self::centavos($q["montoMaximo"] ?? null),
            texto: is_string($q["q"] ?? null) && trim($q["q"]) !== "" ? mb_substr(trim($q["q"]), 0, 100) : null,
            orden: $orden,
            descendente: ($q["direccion"] ?? "desc") !== "asc",
            pagina: max(1, (int) ($q["pagina"] ?? 1)),
            porPagina: min(self::MAX_POR_PAGINA, max(1, (int) ($q["porPagina"] ?? 25))),
        );
    }

    private static function dia(mixed $v): ?string
    {
        return is_string($v) && \DateTimeImmutable::createFromFormat("!Y-m-d", $v) !== false ? $v : null;
    }

    private static function entero(mixed $v): ?int
    {
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    /** Quetzales ("125.50") → centavos. */
    private static function centavos(mixed $v): ?int
    {
        return is_numeric($v) && (float) $v >= 0 ? (int) round((float) $v * 100) : null;
    }
}
