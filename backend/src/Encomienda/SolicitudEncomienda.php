<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Entity\Enum\ModalidadEncomienda;

/** Pedido de registro de una encomienda sin boleto (`POST /api/encomiendas`). */
final readonly class SolicitudEncomienda
{
    /**
     * @param list<ArticuloSolicitado> $articulos
     */
    public function __construct(
        /** Clave de idempotencia (UUID) generada por el cliente. */
        public string $token,
        public int $empresaId,
        /** Estación de origen; null = la del usuario. */
        public ?int $origenId,
        public int $destinoId,
        public ?int $rutaId,
        public int $remitenteId,
        public int $destinatarioId,
        public ModalidadEncomienda $modalidad,
        public array $articulos,
        public ?string $observacion = null,
        public ?int $tipoPagoId = null,
        public ?int $monedaId = null,
        /** A quién se factura (pagada); null = el remitente. */
        public ?int $clienteFacturaId = null,
        /** Contingencia: registrar la pagada sin certificar la factura (queda pendiente). */
        public bool $sinFacturaElectronica = false,
    ) {}

    /**
     * @param array<string, mixed> $datos
     *
     * @throws EncomiendaRechazada
     */
    public static function desdeArray(array $datos): self
    {
        $token = (string) ($datos["token"] ?? "");
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
            throw new EncomiendaRechazada("Falta la clave del registro (token UUID).");
        }
        $modalidad = ModalidadEncomienda::tryFrom((string) ($datos["modalidad"] ?? ""));
        if ($modalidad === null || $modalidad === ModalidadEncomienda::CON_BOLETO) {
            throw new EncomiendaRechazada("Elija si la encomienda se paga ahora, por cobrar o es interna.");
        }
        $observacion = trim((string) ($datos["observacion"] ?? ""));

        return new self(
            token: strtolower($token),
            empresaId: self::entero($datos["empresa"] ?? null, "empresa"),
            origenId: self::entero($datos["origen"] ?? null, "origen", false),
            destinoId: self::entero($datos["destino"] ?? null, "destino"),
            rutaId: self::entero($datos["ruta"] ?? null, "ruta", false),
            remitenteId: self::entero($datos["remitente"] ?? null, "remitente"),
            destinatarioId: self::entero($datos["destinatario"] ?? null, "destinatario"),
            modalidad: $modalidad,
            articulos: ArticuloSolicitado::lista($datos["articulos"] ?? null),
            observacion: $observacion !== "" ? mb_substr($observacion, 0, 255) : null,
            tipoPagoId: self::entero($datos["tipoPago"] ?? null, "tipoPago", false),
            monedaId: self::entero($datos["moneda"] ?? null, "moneda", false),
            clienteFacturaId: self::entero($datos["clienteFactura"] ?? null, "clienteFactura", false),
            sinFacturaElectronica: (bool) ($datos["sinFacturaElectronica"] ?? false),
        );
    }

    /** @throws EncomiendaRechazada */
    public static function entero(mixed $v, string $campo, bool $requerido = true): ?int
    {
        if ($v === null || $v === "") {
            if ($requerido) {
                throw new EncomiendaRechazada("Falta el campo «{$campo}».");
            }

            return null;
        }
        if (is_string($v) && str_contains($v, "/")) {
            $v = substr($v, strrpos($v, "/") + 1);
        }
        if (!is_numeric($v) || (int) $v <= 0) {
            throw new EncomiendaRechazada("El campo «{$campo}» no es válido.");
        }

        return (int) $v;
    }
}
