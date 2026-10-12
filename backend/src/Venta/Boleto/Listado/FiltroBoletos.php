<?php

declare(strict_types=1);

namespace App\Venta\Boleto\Listado;

use App\Entity\Enum\AsientoClase;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Venta\Excepcion\VentaRechazada;

/**
 * Filtros del listado de boletos (el "Boletos" del legado): rango de días
 * (entra el boleto cuya salida o cuya venta cae en él, ambos días
 * incluidos; no aplica si se pide un id) y el resto opcionales. Página,
 * tamaño y orden.
 */
final readonly class FiltroBoletos
{
    /** Como el legado: el rango no pasa de seis meses. */
    public const MAX_DIAS = 186;
    public const POR_PAGINA = [10, 15, 25, 50, 100];
    /** Columnas por las que se puede ordenar. */
    public const ORDENES = ["id", "fecha", "numero", "ruta", "clienteDocumento", "clienteBoleto", "creada"];
    /** Tipo de documento de la venta (ver `ListadoBoletos::documento`). */
    public const DOCUMENTOS = ["factura", "pendiente", "sin_factura", "agencia", "cortesia", "voucher"];

    /**
     * @param list<EstadoBoletoAsiento> $estados
     */
    public function __construct(
        public \DateTimeImmutable $desde,
        public \DateTimeImmutable $hasta,
        public ?int $id = null,
        public ?int $reasignadoDe = null,
        public ?int $salida = null,
        public ?string $documento = null,
        public array $estados = [],
        public ?int $numeroAsiento = null,
        public ?AsientoClase $clase = null,
        public ?string $serieFactura = null,
        public ?int $numeroFactura = null,
        public ?string $origen = null,
        public ?string $destino = null,
        public ?string $referenciaExterna = null,
        public ?string $autorizacion = null,
        public ?int $clienteDocumento = null,
        public ?int $clienteBoleto = null,
        public int $pagina = 1,
        public int $porPagina = 15,
        public string $orden = "id",
        public bool $descendente = true,
    ) {
        if ($hasta < $desde) {
            throw new VentaRechazada("La fecha final no puede ser anterior a la inicial.", "rango_invalido", 400);
        }
        if ($desde->diff($hasta)->days > self::MAX_DIAS) {
            throw new VentaRechazada("El rango de fechas no puede pasar de seis meses.", "rango_invalido", 400);
        }
    }

    /**
     * Sin `desde`/`hasta` (o con uno solo): 30 días antes y después de hoy,
     * como el legado.
     *
     * @param array<string, mixed> $q
     */
    public static function desdeQuery(array $q, \DateTimeImmutable $hoy): self
    {
        $hoy = $hoy->setTime(0, 0);
        $desde = self::dia($q["desde"] ?? null, "la fecha inicial") ?? $hoy->modify("-30 days");
        $hasta = self::dia($q["hasta"] ?? null, "la fecha final") ?? $hoy->modify("+30 days");

        $documento = self::texto($q["documento"] ?? null);
        if ($documento !== null && !in_array($documento, self::DOCUMENTOS, true)) {
            throw new VentaRechazada("El tipo de documento no es válido.", "parametro_invalido", 400);
        }
        $estados = [];
        foreach (array_filter(explode(",", (string) self::texto($q["estado"] ?? null))) as $e) {
            $estados[] = EstadoBoletoAsiento::tryFrom(trim($e)) ?? throw new VentaRechazada("El estado no es válido.", "parametro_invalido", 400);
        }
        $clase = self::texto($q["clase"] ?? null);
        $porPagina = self::entero($q["porPagina"] ?? null, "el tamaño de página") ?? 15;
        $orden = self::texto($q["orden"] ?? null) ?? "id";
        if (!in_array($orden, self::ORDENES, true)) {
            throw new VentaRechazada("No se puede ordenar por esa columna.", "parametro_invalido", 400);
        }

        return new self(
            $desde,
            $hasta,
            id: self::entero($q["id"] ?? null, "el id"),
            reasignadoDe: self::entero($q["reasignado"] ?? null, "el boleto reasignado"),
            salida: self::entero($q["salida"] ?? null, "la salida"),
            documento: $documento,
            estados: array_values(array_unique($estados, SORT_REGULAR)),
            numeroAsiento: self::entero($q["asiento"] ?? null, "el número de asiento"),
            clase: $clase === null ? null : (AsientoClase::tryFrom(strtoupper($clase)) ?? throw new VentaRechazada("La clase de asiento no es válida.", "parametro_invalido", 400)),
            serieFactura: self::texto($q["serie"] ?? null),
            numeroFactura: self::entero($q["factura"] ?? null, "el número de factura"),
            origen: self::texto($q["origen"] ?? null),
            destino: self::texto($q["destino"] ?? null),
            referenciaExterna: self::texto($q["referencia"] ?? null),
            autorizacion: self::texto($q["autorizacion"] ?? null),
            clienteDocumento: self::entero($q["clienteDocumento"] ?? null, "el cliente del documento"),
            clienteBoleto: self::entero($q["clienteBoleto"] ?? null, "el cliente del boleto"),
            pagina: max(1, self::entero($q["pagina"] ?? null, "la página") ?? 1),
            porPagina: in_array($porPagina, self::POR_PAGINA, true) ? $porPagina : 15,
            orden: $orden,
            descendente: ($q["dir"] ?? "desc") !== "asc",
        );
    }

    /** Fin exclusivo del rango (el día siguiente a `hasta`). */
    public function finExclusivo(): \DateTimeImmutable
    {
        return $this->hasta->modify("+1 day");
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

        return $t === "" ? null : mb_substr($t, 0, 100);
    }
}
