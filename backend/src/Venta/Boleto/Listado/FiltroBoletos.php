<?php

declare(strict_types=1);

namespace App\Venta\Boleto\Listado;

use App\Http\Parametros;
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
        /** Id de la estación (enclave) donde sube. */
        public ?int $origen = null,
        /** Id de la estación (enclave) donde baja. */
        public ?int $destino = null,
        public ?string $referenciaExterna = null,
        public ?string $autorizacion = null,
        public ?int $clienteDocumento = null,
        public ?int $clienteBoleto = null,
        public int $pagina = 1,
        public int $porPagina = 15,
        public string $orden = "id",
        public bool $descendente = true,
        /** Id de la empresa de la salida. */
        public ?int $empresa = null,
        /** Solo los boletos de las ventas que creó el usuario de la sesión. */
        public bool $mios = false,
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
        $desde = Parametros::dia($q["desde"] ?? null, "la fecha inicial") ?? $hoy->modify("-30 days");
        $hasta = Parametros::dia($q["hasta"] ?? null, "la fecha final") ?? $hoy->modify("+30 days");

        $documento = Parametros::texto($q["documento"] ?? null);
        if ($documento !== null && !in_array($documento, self::DOCUMENTOS, true)) {
            throw new VentaRechazada("El tipo de documento no es válido.", "parametro_invalido", 400);
        }
        $estados = [];
        foreach (array_filter(explode(",", (string) Parametros::texto($q["estado"] ?? null))) as $e) {
            $estados[] = EstadoBoletoAsiento::tryFrom(trim($e)) ?? throw new VentaRechazada("El estado no es válido.", "parametro_invalido", 400);
        }
        $clase = Parametros::texto($q["clase"] ?? null);
        $porPagina = Parametros::entero($q["porPagina"] ?? null, "el tamaño de página") ?? 15;
        $orden = Parametros::texto($q["orden"] ?? null) ?? "id";
        if (!in_array($orden, self::ORDENES, true)) {
            throw new VentaRechazada("No se puede ordenar por esa columna.", "parametro_invalido", 400);
        }

        return new self(
            $desde,
            $hasta,
            id: Parametros::entero($q["id"] ?? null, "el id"),
            reasignadoDe: Parametros::entero($q["reasignado"] ?? null, "el boleto reasignado"),
            salida: Parametros::entero($q["salida"] ?? null, "la salida"),
            documento: $documento,
            estados: array_values(array_unique($estados, SORT_REGULAR)),
            numeroAsiento: Parametros::entero($q["asiento"] ?? null, "el número de asiento"),
            clase: $clase === null ? null : (AsientoClase::tryFrom(strtoupper($clase)) ?? throw new VentaRechazada("La clase de asiento no es válida.", "parametro_invalido", 400)),
            serieFactura: Parametros::texto($q["serie"] ?? null),
            numeroFactura: Parametros::entero($q["factura"] ?? null, "el número de factura"),
            origen: Parametros::entero($q["origen"] ?? null, "el origen"),
            destino: Parametros::entero($q["destino"] ?? null, "el destino"),
            referenciaExterna: Parametros::texto($q["referencia"] ?? null),
            autorizacion: Parametros::texto($q["autorizacion"] ?? null),
            clienteDocumento: Parametros::entero($q["clienteDocumento"] ?? null, "el cliente del documento"),
            clienteBoleto: Parametros::entero($q["clienteBoleto"] ?? null, "el cliente del boleto"),
            pagina: max(1, Parametros::entero($q["pagina"] ?? null, "la página") ?? 1),
            porPagina: in_array($porPagina, self::POR_PAGINA, true) ? $porPagina : 15,
            orden: $orden,
            descendente: ($q["dir"] ?? "desc") !== "asc",
            empresa: Parametros::entero($q["empresa"] ?? null, "la empresa"),
            mios: Parametros::bandera($q["mios"] ?? null),
        );
    }

    /** Fin exclusivo del rango (el día siguiente a `hasta`). */
    public function finExclusivo(): \DateTimeImmutable
    {
        return $this->hasta->modify("+1 day");
    }
}
