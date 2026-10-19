<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

/**
 * Una encomienda tal como la leen los reportes (ADR-035): datos planos, sin
 * entidades. Importes en centavos. `entrega*` y `anulacion*` salen de la
 * bitácora (quién y cuándo).
 */
final readonly class LineaEncomienda
{
    public function __construct(
        public int $id,
        public string $codigo,
        public \DateTimeImmutable $creada,
        public string $empresa,
        public string $origen,
        public string $destino,
        public ?string $destinoAlias,
        public string $estado,
        public string $modalidad,
        public int $total,
        public string $moneda,
        public ?int $ventaId,
        public ?string $serie,
        public ?int $dte,
        public bool $facturaAnulada,
        public ?int $numeroAcceso,
        public string $estadoFacturacion,
        public string $usuario,
        public string $estacion,
        public string $remitente,
        public string $destinatario,
        public ?string $receptor,
        public ?string $clienteFactura,
        public string $contenido,
        public int $bultos,
        public string $tipo,
        public ?int $salidaId,
        public ?\DateTimeImmutable $salidaFecha,
        public ?string $bus,
        /** @var list<string> */
        public array $pilotos,
        public ?string $ruta,
        public ?string $rutaSalida,
        public ?\DateTimeImmutable $entregadaEn,
        public ?string $entregadaPor,
        public ?\DateTimeImmutable $anuladaEn,
        public ?string $anuladaPor,
        public ?string $motivoAnulacion,
        public ?string $observacion,
    ) {}

    public function esPorCobrar(): bool
    {
        return $this->modalidad === "por_cobrar";
    }

    public function esPagada(): bool
    {
        return $this->modalidad === "pagada";
    }

    public function anulada(): bool
    {
        return $this->estado === "anulada";
    }

    /** Ya se cobró la por cobrar (al entregarla). */
    public function cobrada(): bool
    {
        return $this->esPorCobrar() && $this->estado === "entregada";
    }

    /** Lo que se cobró al recibirla (pagada o con boleto). */
    public function pagado(): int
    {
        return $this->anulada() || !in_array($this->modalidad, ["pagada", "con_boleto"], true) ? 0 : $this->total;
    }

    /** Lo que falta o faltaba cobrar al entregarla. */
    public function porCobrar(): int
    {
        return $this->anulada() || !$this->esPorCobrar() ? 0 : $this->total;
    }

    /** "Serie-número" del DTE, o null si no hay factura propia. */
    public function factura(): ?string
    {
        return $this->dte === null ? null : sprintf("%s-%s", $this->serie, $this->dte);
    }

    /** Cómo se paga: la columna "Documento" del legado. */
    public function documento(): string
    {
        return match ($this->modalidad) {
            "pagada" => $this->dte !== null ? sprintf("Factura (%s)", $this->factura()) : ($this->numeroAcceso !== null ? "Factura (contingencia)" : "Factura"),
            "por_cobrar" => $this->dte !== null ? sprintf("Por cobrar · Fact. %s", $this->factura()) : "Por cobrar",
            "interna" => "Guía interna",
            "con_boleto" => sprintf("Con boleto (venta %s)", $this->ventaId ?? "?"),
            default => $this->modalidad,
        };
    }

    public function estadoEtiqueta(): string
    {
        return match ($this->estado) {
            "recibida" => "Recibida",
            "embarcada" => "Embarcada",
            "transito" => "Tránsito",
            "desembarcada" => "Desembarcada",
            "entregada" => "Entregada",
            "anulada" => "Anulada",
            default => ucfirst($this->estado),
        };
    }

    public function diaCreada(): string
    {
        return $this->creada->format("Y-m-d");
    }
}
