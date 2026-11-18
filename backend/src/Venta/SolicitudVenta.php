<?php

declare(strict_types=1);

namespace App\Venta;

use App\Encomienda\ArticuloSolicitado;
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
        public int $salidaId,
        /** Trayecto que viaja el cliente; null = el del salida. */
        public ?int $trayectoId,
        public array $asientos,
        /** A quién se factura (y pasajero por defecto). */
        public int $clienteId,
        /** Estación donde se vende (taquilla). */
        public ?int $estacionId = null,
        public ?string $observacion = null,
        /** Cobrar la tarifa del trayecto completo de la salida aunque viaje un tramo intermedio. */
        public bool $cobrarTrayectoCompleto = false,
        public ?int $tipoPagoId = null,
        public ?int $monedaId = null,
        public bool $enviarCorreo = false,
        public bool $cortesia = false,
        /** Contingencia: registrar la venta sin certificar la factura (queda pendiente). */
        public bool $sinFacturaElectronica = false,
        /**
         * Encomiendas que lleva el cliente en la bodega (ADR-035): se suman al
         * total y van en la misma factura.
         *
         * @var list<array{articulos: list<ArticuloSolicitado>, observacion: ?string}>
         */
        public array $encomiendas = [],
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

        $encomiendas = [];
        foreach (array_values((array) ($datos["encomiendas"] ?? [])) as $e) {
            $e = is_array($e) ? $e : [];
            $obs = trim((string) ($e["observacion"] ?? ""));
            $encomiendas[] = [
                "articulos" => ArticuloSolicitado::lista($e["articulos"] ?? null),
                "observacion" => $obs !== "" ? mb_substr($obs, 0, 255) : null,
            ];
        }
        if (count($encomiendas) > 5) {
            throw new VentaRechazada("Demasiadas encomiendas en una sola venta.");
        }

        return new self(
            token: strtolower($token),
            salidaId: $entero($datos["salida"] ?? null, "salida"),
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
            encomiendas: $encomiendas,
        );
    }
}
