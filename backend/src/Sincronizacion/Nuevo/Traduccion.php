<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\ModalidadEncomienda;

/**
 * Lo del modelo nuevo en los códigos del legado (ADR-042). Puro: lo inverso
 * de lo que lee la migración (`Migrador`, `Mapeador`, `EncomiendaLegada`).
 */
final class Traduccion
{
    /** `boleto.estado_id` que ocupan el asiento en el legado (emitido, chequeado, en tránsito). */
    public const OCUPAN_LEGADO = [1, 2, 3];

    /** `reservacion.estado_id` vigente (emitida). */
    public const RESERVACION_VIGENTE = 1;

    /** Prefijo de `identificador_web` de lo que FDN escribe en el legado. */
    public const PREFIJO = "FDN:";

    /** `servicio_estacion` del legado. */
    public const SERVICIO_BOLETO = 1;
    public const SERVICIO_ENCOMIENDA = 2;

    /** `moneda` del legado: quetzal. */
    public const GTQ = 1;

    /**
     * `boleto_estado` del legado. Finalizado no existe allá (el boleto se
     * queda en tránsito) y pospuesto es lo más parecido a cancelado: libera
     * el asiento y la venta sigue.
     */
    public static function estadoBoleto(EstadoBoletoAsiento $e): int
    {
        return match ($e) {
            EstadoBoletoAsiento::EMITIDO => 1,
            EstadoBoletoAsiento::CHEQUEADO => 2,
            EstadoBoletoAsiento::TRANSITO, EstadoBoletoAsiento::FINALIZADO => 3,
            EstadoBoletoAsiento::ANULADO => 4,
            EstadoBoletoAsiento::REASIGNADO => 5,
            EstadoBoletoAsiento::CANCELADO, EstadoBoletoAsiento::POSPUESTO => 6,
        };
    }

    public static function ocupa(int $estadoLegado): bool
    {
        return in_array($estadoLegado, self::OCUPAN_LEGADO, true);
    }

    /**
     * `boleto_documento_tipo` del legado: factura, cortesía, voucher de
     * agencia o voucher de internet.
     */
    public static function tipoDocumentoBoleto(CanalVenta $canal, bool $cortesia): int
    {
        return match (true) {
            $cortesia => 3,
            $canal === CanalVenta::AGENCIA => 5,
            $canal === CanalVenta::WEB => 8,
            default => 1,
        };
    }

    /** `encomienda_estado` del legado (la anulada es la 6; la 7, cancelada, no se usa aquí). */
    public static function estadoEncomienda(EstadoEncomienda $e): int
    {
        return match ($e) {
            EstadoEncomienda::RECIBIDA => 1,
            EstadoEncomienda::EMBARCADA => 2,
            EstadoEncomienda::TRANSITO => 3,
            EstadoEncomienda::DESEMBARCADA => 4,
            EstadoEncomienda::ENTREGADA => 5,
            EstadoEncomienda::ANULADA => 6,
        };
    }

    /**
     * `encomienda_documento_tipo` del legado: factura, por cobrar o interna.
     * La que va con boleto se factura con la venta: factura.
     */
    public static function tipoDocumentoEncomienda(ModalidadEncomienda $m): int
    {
        return match ($m) {
            ModalidadEncomienda::POR_COBRAR => 2,
            ModalidadEncomienda::INTERNA => 4,
            ModalidadEncomienda::PAGADA, ModalidadEncomienda::CON_BOLETO => 1,
        };
    }

    /** `encomienda_tipo` del legado: especial (2) si es un especial del catálogo; si no, paquete (3). */
    public static function tipoEncomienda(?string $especialLegado): int
    {
        return $especialLegado !== null && $especialLegado !== "" ? 2 : 3;
    }

    /** Centavos a quetzales con dos decimales (`decimal(7,2)` del legado). */
    public static function quetzales(int|string|null $centavos): string
    {
        return number_format(((int) $centavos) / 100, 2, ".", "");
    }

    /**
     * Nombre y apellidos como los parte el legado (`nombre1`, `apellido1`…)
     * más el nombre completo de `cliente.nombre` (100).
     *
     * @return array{nombre: string, nombre1: ?string, nombre2: ?string, apellido1: ?string, apellido2: ?string}
     */
    public static function nombreCliente(?string $nombre, ?string $apellido): array
    {
        $partes = static fn(?string $t) => array_values(array_filter(preg_split('/\s+/', trim((string) $t)) ?: [], static fn(string $p) => $p !== ""));
        $nombres = $partes($nombre);
        $apellidos = $partes($apellido === "S/N" ? null : $apellido);
        $corto = static fn(array $p, int $i, bool $resto = false) => isset($p[$i]) ? mb_substr($resto ? implode(" ", array_slice($p, $i)) : $p[$i], 0, 50) : null;
        $completo = trim(implode(" ", [...$nombres, ...$apellidos]));

        return [
            "nombre" => mb_substr($completo === "" ? "S/N" : $completo, 0, 100),
            "nombre1" => $corto($nombres, 0),
            "nombre2" => $corto($nombres, 1, true),
            "apellido1" => $corto($apellidos, 0),
            "apellido2" => $corto($apellidos, 1, true),
        ];
    }

    /** NIT como lo guarda el legado: sin guiones; consumidor final, "CF". */
    public static function nit(?string $nit): string
    {
        $nit = strtoupper((string) preg_replace('/[\s-]+/', "", (string) $nit));

        return $nit === "" || $nit === "C/F" ? "CF" : mb_substr($nit, 0, 20);
    }
}
