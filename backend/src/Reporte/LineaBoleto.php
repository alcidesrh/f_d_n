<?php

declare(strict_types=1);

namespace App\Reporte;

/**
 * Un boleto (asiento vendido) tal como lo necesitan los reportes de venta.
 * Los importes van en centavos; `centavos` es el precio del boleto y
 * `cobrado()` lo que realmente entró (0 si es voucher o cortesía).
 */
final readonly class LineaBoleto
{
    public function __construct(
        public int $boletoId,
        public int $ventaId,
        /** Momento de la venta (hora local). */
        public \DateTimeImmutable $vendida,
        public string $usuario,
        public string $usuarioNombre,
        public bool $anulado,
        public int $centavos,
        public string $moneda,
        public bool $sinCobro,
        public int $salidaId,
        public \DateTimeImmutable $salida,
        public ?string $bus,
        public ?string $piloto,
        /** "Origen - Destino" del trayecto de la salida. */
        public string $ruta,
        public string $origen,
        public string $destino,
        public int $asiento,
        public ?int $facturaId,
        public ?string $serie,
        public ?int $dte,
        /** `certificada`, `pendiente` o `no_aplica` (`EstadoFacturacion`). */
        public string $estadoFacturacion,
        public bool $tarjeta,
        public ?string $autorizacion,
        public ?string $referenciaExterna,
    ) {}

    public function cobrado(): int
    {
        return $this->sinCobro ? 0 : $this->centavos;
    }

    /** `Serie Número` de la factura certificada, o `null`. */
    public function factura(): ?string
    {
        return $this->dte === null ? null : trim(sprintf("%s %d", $this->serie, $this->dte));
    }

    public function diaVenta(): string
    {
        return $this->vendida->format("Y-m-d");
    }

    public function diaSalida(): string
    {
        return $this->salida->format("Y-m-d");
    }
}
