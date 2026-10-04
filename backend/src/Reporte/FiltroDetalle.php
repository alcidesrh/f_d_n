<?php

declare(strict_types=1);

namespace App\Reporte;

/** Parámetros del "Detalle de factura de boletos": rango de días (ambos incluidos) y filtros opcionales. */
final readonly class FiltroDetalle
{
    /** Máximo de días por reporte: acota el tamaño del PDF/Excel. */
    public const MAX_DIAS = 62;

    public function __construct(
        public \DateTimeImmutable $desde,
        public \DateTimeImmutable $hasta,
        public ?int $estacionId,
        public ?int $empresaId,
        public ?string $autorizacion,
        public ?string $referencia,
        public bool $soloTarjetas,
        public bool $soloReferencias,
    ) {
        if ($hasta < $desde) {
            throw new ReporteRechazado("La fecha final no puede ser anterior a la inicial.", "rango_invalido", 400);
        }
        if ($desde->diff($hasta)->days >= self::MAX_DIAS) {
            throw new ReporteRechazado(sprintf("El rango no puede pasar de %d días.", self::MAX_DIAS), "rango_invalido", 400);
        }
    }

    /** @param array<string, mixed> $q */
    public static function desdeQuery(array $q): self
    {
        $desde = Parametros::dia($q["desde"] ?? null, "la fecha inicial");

        return new self(
            $desde,
            isset($q["hasta"]) && $q["hasta"] !== "" ? Parametros::dia($q["hasta"], "la fecha final") : $desde,
            Parametros::entero($q["estacion"] ?? null, "la estación"),
            Parametros::entero($q["empresa"] ?? null, "la empresa"),
            Parametros::texto($q["autorizacion"] ?? null),
            Parametros::texto($q["referencia"] ?? null),
            Parametros::bandera($q["soloTarjetas"] ?? null),
            Parametros::bandera($q["soloReferencias"] ?? null),
        );
    }

    public function conAlcance(?int $estacionId, ?int $empresaId): self
    {
        return new self(
            $this->desde,
            $this->hasta,
            $estacionId ?? $this->estacionId,
            $empresaId ?? $this->empresaId,
            $this->autorizacion,
            $this->referencia,
            $this->soloTarjetas,
            $this->soloReferencias,
        );
    }

    public function rotulo(): string
    {
        return $this->desde == $this->hasta
            ? $this->desde->format("d/m/Y")
            : sprintf("%s al %s", $this->desde->format("d/m/Y"), $this->hasta->format("d/m/Y"));
    }
}
