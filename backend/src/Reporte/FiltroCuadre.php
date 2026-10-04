<?php

declare(strict_types=1);

namespace App\Reporte;

/** Parámetros del "Cuadre de venta de boletos": un día, una estación, una empresa y una moneda. */
final readonly class FiltroCuadre
{
    public function __construct(
        public \DateTimeImmutable $fecha,
        public ?int $estacionId,
        public ?int $empresaId,
        public string $moneda,
    ) {}

    /** @param array<string, mixed> $q */
    public static function desdeQuery(array $q): self
    {
        $moneda = strtoupper(Parametros::texto($q["moneda"] ?? null, 3) ?? "");
        if (!preg_match('/^[A-Z]{3}$/', $moneda)) {
            throw new ReporteRechazado("Elige la moneda del reporte.", "moneda_invalida", 400);
        }

        return new self(
            Parametros::dia($q["fecha"] ?? null, "la fecha"),
            Parametros::entero($q["estacion"] ?? null, "la estación"),
            Parametros::entero($q["empresa"] ?? null, "la empresa"),
            $moneda,
        );
    }

    public function conAlcance(?int $estacionId, ?int $empresaId): self
    {
        return new self($this->fecha, $estacionId ?? $this->estacionId, $empresaId ?? $this->empresaId, $this->moneda);
    }
}
