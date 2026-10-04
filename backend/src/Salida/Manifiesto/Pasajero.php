<?php

declare(strict_types=1);

namespace App\Salida\Manifiesto;

/** Un boleto vivo de una salida, tal como se imprime en los manifiestos. */
final readonly class Pasajero
{
    public function __construct(
        public int $boletoId,
        public string $nombre,
        public ?string $nacionalidad,
        /** Factura (`serie número`), `Voucher` o `Cortesía`. */
        public ?string $documento,
        public int $asiento,
        public string $clase,
        public string $sube,
        public string $baja,
        /** Valor de `EstadoBoletoAsiento`. */
        public string $estado,
        public string $canal,
        public int $centavos,
        public string $moneda,
        /** Voucher o cortesía: el boleto no se cobró. */
        public bool $sinCobro,
        /** Dónde se emitió: estación, agencia o página web. */
        public string $emitidoEn,
        public ?string $observacion,
    ) {}

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            "transito" => "Tránsito",
            default => ucfirst($this->estado),
        };
    }

    /** Importe cobrado formateado (`Q 150.00`; los boletos sin cobro valen 0). */
    public function importe(): string
    {
        return Manifiesto::importe($this->cobrado(), $this->moneda);
    }

    /** Importe que cuenta para el ingreso (los boletos sin cobro valen 0). */
    public function cobrado(): int
    {
        return $this->sinCobro ? 0 : $this->centavos;
    }
}
