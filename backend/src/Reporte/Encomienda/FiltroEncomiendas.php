<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

use App\Reporte\Parametros;
use App\Reporte\ReporteRechazado;

/**
 * Parámetros de los reportes de encomiendas: rango de días (ambos
 * incluidos; los de un solo día usan `desde`), estación, empresa, origen,
 * destino, salida y las dos casillas del legado. Cada reporte usa los suyos.
 */
final readonly class FiltroEncomiendas
{
    /** Máximo de días por reporte, como en el detalle de boletos. */
    public const MAX_DIAS = 62;

    public function __construct(
        public ?\DateTimeImmutable $desde = null,
        public ?\DateTimeImmutable $hasta = null,
        public ?int $estacionId = null,
        public ?int $empresaId = null,
        public ?int $origenId = null,
        public ?int $destinoId = null,
        public ?int $salidaId = null,
        public bool $soloFacturado = false,
        public bool $soloPorCobrar = false,
    ) {
        if ($desde !== null && $hasta !== null) {
            if ($hasta < $desde) {
                throw new ReporteRechazado("La fecha final no puede ser anterior a la inicial.", "rango_invalido", 400);
            }
            if ($desde->diff($hasta)->days >= self::MAX_DIAS) {
                throw new ReporteRechazado(sprintf("El rango no puede pasar de %d días.", self::MAX_DIAS), "rango_invalido", 400);
            }
        }
    }

    /**
     * @param array<string, mixed> $q
     * @param bool $conFechas el reporte exige `desde` (y `hasta` opcional)
     */
    public static function desdeQuery(array $q, bool $conFechas = true): self
    {
        $desde = $conFechas ? Parametros::dia($q["desde"] ?? null, "la fecha") : null;

        return new self(
            $desde,
            $desde === null ? null : (isset($q["hasta"]) && $q["hasta"] !== "" ? Parametros::dia($q["hasta"], "la fecha final") : $desde),
            Parametros::entero($q["estacion"] ?? null, "la estación"),
            Parametros::entero($q["empresa"] ?? null, "la empresa"),
            Parametros::entero($q["origen"] ?? null, "el origen"),
            Parametros::entero($q["destino"] ?? null, "el destino"),
            Parametros::entero($q["salida"] ?? null, "la salida"),
            Parametros::bandera($q["soloFacturado"] ?? null),
            Parametros::bandera($q["soloPorCobrar"] ?? null),
        );
    }

    /** La estación y la empresa del usuario, si las tiene, mandan sobre las de la query. */
    public function conAlcance(?int $estacionId, ?int $empresaId): self
    {
        return new self(
            $this->desde,
            $this->hasta,
            $estacionId ?? $this->estacionId,
            $empresaId ?? $this->empresaId,
            $this->origenId,
            $this->destinoId,
            $this->salidaId,
            $this->soloFacturado,
            $this->soloPorCobrar,
        );
    }

    /** Fin exclusivo del rango (el día siguiente a `hasta`). */
    public function fin(): \DateTimeImmutable
    {
        return ($this->hasta ?? $this->desde ?? new \DateTimeImmutable("today"))->modify("+1 day");
    }

    public function rotulo(): string
    {
        if ($this->desde === null) {
            return "";
        }

        return $this->hasta === null || $this->desde == $this->hasta
            ? $this->desde->format("d/m/Y")
            : sprintf("%s al %s", $this->desde->format("d/m/Y"), $this->hasta->format("d/m/Y"));
    }

    public function exigir(string $campo, string $texto): void
    {
        if ($this->{$campo} === null) {
            throw new ReporteRechazado(sprintf("Elija %s.", $texto), "parametro_invalido", 400);
        }
    }
}
