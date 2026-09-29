<?php

declare(strict_types=1);

namespace App\Venta;

use App\Venta\Excepcion\VentaRechazada;

/**
 * Pedido de venta de la taquilla o de una agencia (`POST /api/venta/ventas`).
 */
final readonly class SolicitudVenta
{
    /**
     * @param list<array{asiento: int, cliente: ?int}> $asientos asiento y, opcional, su pasajero (si no, el cliente de la venta)
     */
    public function __construct(
        /** Clave de idempotencia (UUID) generada por el cliente para esta venta. */
        public string $token,
        public int $recorridoId,
        /** Trayecto que viaja el cliente; null = el del recorrido. */
        public ?int $trayectoId,
        public array $asientos,
        /** A quién se factura (y pasajero por defecto). */
        public int $clienteId,
        /** Estación donde se vende (taquilla). */
        public ?int $estacionId = null,
        public ?string $observacion = null,
        /** Cobrar la tarifa del trayecto completo del recorrido aunque viaje un subtrayecto. */
        public bool $cobrarTrayectoCompleto = false,
        public ?int $tipoPagoId = null,
        public ?int $monedaId = null,
        public bool $enviarCorreo = false,
        public bool $cortesia = false,
        /** Contingencia: registrar la venta sin certificar la factura (queda pendiente). */
        public bool $sinFacturaElectronica = false,
    ) {}

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArray(array $datos): self
    {
        $entero = static function (mixed $v, string $campo, bool $requerido = true): ?int {
            if ($v === null || $v === "") {
                if ($requerido) {
                    throw new VentaRechazada("Falta el campo «{$campo}».");
                }

                return null;
            }
            if (is_string($v) && str_contains($v, "/")) {
                $v = substr($v, strrpos($v, "/") + 1);
            }
            if (!is_numeric($v) || (int) $v <= 0) {
                throw new VentaRechazada("El campo «{$campo}» no es válido.");
            }

            return (int) $v;
        };

        $token = (string) ($datos["token"] ?? "");
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
            throw new VentaRechazada("Falta la clave de la venta (token UUID).");
        }

        $asientos = [];
        foreach ((array) ($datos["asientos"] ?? []) as $i => $a) {
            $a = is_array($a) ? $a : ["asiento" => $a];
            $asientos[] = [
                "asiento" => $entero($a["asiento"] ?? null, "asientos[{$i}].asiento"),
                "cliente" => $entero($a["cliente"] ?? null, "asientos[{$i}].cliente", false),
            ];
        }
        if ($asientos === []) {
            throw new VentaRechazada("Seleccione al menos un asiento.");
        }
        if (count($asientos) > 60) {
            throw new VentaRechazada("Demasiados asientos en una sola venta.");
        }

        $observacion = trim((string) ($datos["observacion"] ?? ""));

        return new self(
            token: strtolower($token),
            recorridoId: $entero($datos["recorrido"] ?? null, "recorrido"),
            trayectoId: $entero($datos["trayecto"] ?? null, "trayecto", false),
            asientos: $asientos,
            clienteId: $entero($datos["cliente"] ?? null, "cliente"),
            estacionId: $entero($datos["estacion"] ?? null, "estacion", false),
            observacion: $observacion !== "" ? mb_substr($observacion, 0, 255) : null,
            cobrarTrayectoCompleto: (bool) ($datos["cobrarTrayectoCompleto"] ?? false),
            tipoPagoId: $entero($datos["tipoPago"] ?? null, "tipoPago", false),
            monedaId: $entero($datos["moneda"] ?? null, "moneda", false),
            enviarCorreo: (bool) ($datos["enviarCorreo"] ?? false),
            cortesia: (bool) ($datos["cortesia"] ?? false),
            sinFacturaElectronica: (bool) ($datos["sinFacturaElectronica"] ?? false),
        );
    }
}
