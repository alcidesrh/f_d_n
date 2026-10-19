<?php

declare(strict_types=1);

namespace App\Encomienda;

/** Pedido de entrega (`POST /api/encomiendas/{id}/entregar`). */
final readonly class EntregaEncomienda
{
    public function __construct(
        /** Quien la retira; null = el destinatario. */
        public ?int $receptorId = null,
        /** Por cobrar: a quién se factura; null = quien la retira. */
        public ?int $clienteFacturaId = null,
        public ?int $tipoPagoId = null,
        public ?int $monedaId = null,
        /** Por cobrar en contingencia: cobrar sin certificar la factura (queda pendiente). */
        public bool $sinFacturaElectronica = false,
    ) {}

    /**
     * @param array<string, mixed> $datos
     *
     * @throws EncomiendaRechazada
     */
    public static function desdeArray(array $datos): self
    {
        return new self(
            receptorId: SolicitudEncomienda::entero($datos["receptor"] ?? null, "receptor", false),
            clienteFacturaId: SolicitudEncomienda::entero($datos["clienteFactura"] ?? null, "clienteFactura", false),
            tipoPagoId: SolicitudEncomienda::entero($datos["tipoPago"] ?? null, "tipoPago", false),
            monedaId: SolicitudEncomienda::entero($datos["moneda"] ?? null, "moneda", false),
            sinFacturaElectronica: (bool) ($datos["sinFacturaElectronica"] ?? false),
        );
    }
}
