<?php

declare(strict_types=1);

namespace App\Sincronizacion\Legado;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\OperacionBoleto;
use App\Migration\Mapeador;
use App\Migration\Salida\DependenciasLegado;
use App\Sincronizacion\Nuevo\Traduccion;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Lo que cambió en el legado en boletos que ya están aquí (ADR-042). La
 * migración trae los boletos que faltan; esto pone al día los que ya
 * estaban: estado (anulado, chequeado, reasignado…), de qué boleto viene una
 * reasignación y la factura que se certificó después de vender.
 *
 * Escribe con DBAL (no vuelve al legado) y anota cada cambio en la bitácora
 * del boleto con `detalle.legado`.
 */
class ActualizadorBoletos
{
    /** `boleto.estado_id` del legado → estado de aquí (el mismo mapa de la migración). */
    private const ESTADOS = [
        1 => EstadoBoletoAsiento::EMITIDO,
        2 => EstadoBoletoAsiento::CHEQUEADO,
        3 => EstadoBoletoAsiento::TRANSITO,
        4 => EstadoBoletoAsiento::ANULADO,
        5 => EstadoBoletoAsiento::REASIGNADO,
        6 => EstadoBoletoAsiento::CANCELADO,
    ];

    /** Estados que no cuentan en el total de la venta (como en la migración). */
    private const SIN_COBRO = ["anulado", "reasignado"];

    public function __construct(
        private readonly Connection $db,
        private readonly ConexionLegado $legado,
        private readonly Mapeador $mapeador,
        private readonly DependenciasLegado $dependencias,
    ) {}

    /**
     * @param list<int> $legados ids de boletos del legado que cambiaron
     *
     * @return array<string, int> contadores
     */
    public function actualizar(array $legados): array
    {
        $contadores = ["boleto_actualizado" => 0, "boleto_reasignacion" => 0, "venta_facturada" => 0];
        if ($legados === []) {
            return $contadores;
        }
        $aqui = [];
        foreach ($this->db->fetchAllAssociative(
            "SELECT b.id, b.legacy_id, b.estado, b.reasignado_de_id, b.boleto_venta_id, v.factura_id
               FROM boleto_asiento b JOIN boleto_venta v ON v.id = b.boleto_venta_id
              WHERE b.legacy_id IN (:ids)",
            ["ids" => array_map("strval", $legados)],
            ["ids" => ArrayParameterType::STRING],
        ) as $fila) {
            $aqui[(string) $fila["legacy_id"]] = $fila;
        }
        if ($aqui === []) {
            return $contadores;
        }

        $ventas = [];
        foreach ($this->filasLegado(array_keys($aqui)) as $old) {
            $b = $aqui[(string) $old["id"]];
            $this->db->transactional(function () use ($old, $b, &$contadores, &$ventas) {
                if ($this->estado($old, $b)) {
                    $contadores["boleto_actualizado"]++;
                    $ventas[(int) $b["boleto_venta_id"]] = true;
                }
                if ($this->reasignacion($old, $b)) {
                    $contadores["boleto_reasignacion"]++;
                }
                if ($b["factura_id"] === null && $this->factura($old, (int) $b["boleto_venta_id"])) {
                    $contadores["venta_facturada"]++;
                }
            });
        }
        if ($ventas !== []) {
            $this->db->executeStatement(
                "UPDATE boleto_venta v SET total_monto = COALESCE((
                     SELECT SUM(b.precio_monto) FROM boleto_asiento b
                     WHERE b.boleto_venta_id = v.id AND b.estado NOT IN (:libres)), 0)
                 WHERE v.id IN (:ids) AND v.factura_id IS NOT NULL",
                ["libres" => self::SIN_COBRO, "ids" => array_keys($ventas)],
                ["libres" => ArrayParameterType::STRING, "ids" => ArrayParameterType::INTEGER],
            );
        }

        return $contadores;
    }

    /**
     * El estado del legado, si es otro. Lo que allá no se distingue no se
     * pisa: finalizado aquí es tránsito allá y pospuesto es cancelado.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $b
     */
    private function estado(array $old, array $b): bool
    {
        $nuevo = self::ESTADOS[(int) $old["estado_id"]] ?? null;
        $actual = EstadoBoletoAsiento::from((string) $b["estado"]);
        if ($nuevo === null || Traduccion::estadoBoleto($actual) === (int) $old["estado_id"]) {
            return false;
        }
        $this->db->executeStatement("UPDATE boleto_asiento SET estado = :e WHERE id = :id", ["e" => $nuevo->value, "id" => $b["id"]]);
        $this->db->insert("bitacora", [
            "entidad" => "boleto",
            "registro_id" => $b["id"],
            "operacion" => self::operacion($nuevo)->value,
            "usuario_id" => null,
            "usuario_nombre" => mb_substr((string) ($old["usuario_nombre"] ?? "Sistema anterior"), 0, 100),
            "fecha" => (string) ($old["fecha_actualizacion"] ?? $old["fecha_creacion"]),
            "detalle" => json_encode(["de" => $actual->value, "a" => $nuevo->value, "legado" => "estado:" . $old["id"]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $b
     */
    private function reasignacion(array $old, array $b): bool
    {
        if (empty($old["reasignado_id"]) || $b["reasignado_de_id"] !== null) {
            return false;
        }

        return $this->db->executeStatement(
            "UPDATE boleto_asiento SET reasignado_de_id = (SELECT id FROM boleto_asiento WHERE legacy_id = :original) WHERE id = :id
                AND EXISTS (SELECT 1 FROM boleto_asiento WHERE legacy_id = :original2)",
            ["original" => (string) $old["reasignado_id"], "original2" => (string) $old["reasignado_id"], "id" => $b["id"]],
        ) > 0;
    }

    /**
     * La factura del legado se certificó después de que el boleto llegó
     * aquí: se crea y se le pone a la venta (o se toma la que ya existe con
     * ese UUID).
     *
     * @param array<string, mixed> $old
     */
    private function factura(array $old, int $venta): bool
    {
        if (!Mapeador::facturaCertificada($old)) {
            return false;
        }
        $uuid = strtolower(trim((string) $old["fg_uuid"]));
        $factura = $this->db->fetchOne("SELECT id FROM factura WHERE uuid = :u", ["u" => $uuid]);
        if ($factura === false) {
            $emisor = $this->dependencias->empresa($old["fg_empresa_id"] ?? null);
            $datosEmisor = $emisor === null ? false : $this->db->fetchAssociative("SELECT nit, nombre, nombre_comercial FROM empresa WHERE id = :id", ["id" => $emisor]);
            $comprador = $this->db->fetchOne("SELECT cliente_id FROM boleto_venta WHERE id = :id", ["id" => $venta]);
            $datos = $this->mapeador->factura(
                $old,
                $datosEmisor ?: ["nit" => null, "nombre" => "Sin emisor", "nombre_comercial" => null],
                $this->dependencias->datosCliente($comprador === false ? null : (int) $comprador),
            );
            if ($datos === null) {
                return false;
            }
            $campos = implode(", ", array_keys($datos));
            $args = implode(", ", array_map(static fn($k) => ":{$k}", array_keys($datos)));
            $factura = $this->db->fetchOne("INSERT INTO factura ({$campos}) VALUES ({$args}) RETURNING id", $datos);
        }
        $this->db->executeStatement(
            "UPDATE boleto_venta SET factura_id = :f, estado_facturacion = 'certificada' WHERE id = :id AND factura_id IS NULL",
            ["f" => $factura, "id" => $venta],
        );

        return true;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function filasLegado(array $ids): array
    {
        return $this->legado->filas(
            "SELECT b.id, b.estado_id, b.reasignado_id,
                    CONVERT(varchar(19), b.fecha_creacion, 120) AS fecha_creacion,
                    CONVERT(varchar(19), b.fecha_actualizacion, 120) AS fecha_actualizacion,
                    LEFT(LTRIM(RTRIM(CONCAT(u.names, ' ', u.surnames))), 100) AS usuario_nombre,
                    fg.sAutorizacionUUIDsat AS fg_uuid, fg.sNumeroDTEsat AS fg_dte, fg.sSerieDTEsat AS fg_serie,
                    CONVERT(varchar(19), fg.fecha, 120) AS fg_fecha,
                    CONVERT(varchar(19), fg.sFechaCertificaDTEsat, 120) AS fg_certificada,
                    fg.importeTotal AS fg_total, fl.empresa_id AS fg_empresa_id
               FROM boleto b
               LEFT JOIN custom_user u ON u.id = COALESCE(b.usuario_actualizacion_id, b.usuario_creacion_id)
               LEFT JOIN factura_generada fg ON fg.id = b.factura_generada_id
               LEFT JOIN factura fl ON fl.id = fg.factura_id
              WHERE b.id IN (" . implode(",", array_map("intval", $ids)) . ")",
        );
    }

    private static function operacion(EstadoBoletoAsiento $e): OperacionBoleto
    {
        return match ($e) {
            EstadoBoletoAsiento::ANULADO => OperacionBoleto::ANULADO,
            EstadoBoletoAsiento::REASIGNADO => OperacionBoleto::REASIGNADO,
            EstadoBoletoAsiento::CHEQUEADO => OperacionBoleto::CHEQUEADO,
            EstadoBoletoAsiento::TRANSITO => OperacionBoleto::TRANSITO,
            EstadoBoletoAsiento::CANCELADO => OperacionBoleto::CANCELADO,
            EstadoBoletoAsiento::FINALIZADO => OperacionBoleto::FINALIZADO,
            EstadoBoletoAsiento::POSPUESTO => OperacionBoleto::POSPUESTO,
            EstadoBoletoAsiento::EMITIDO => OperacionBoleto::REVERTIDO,
        };
    }
}
