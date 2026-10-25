<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Sincronizacion\Identidades;
use App\Sincronizacion\Legado\ConexionLegado;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Lleva al legado los cambios anotados en `sync_cambio` (ADR-042). Lee el
 * estado actual de aquí, no el cambio: repetir un cambio no hace daño y
 * varios del mismo registro se llevan una vez.
 *
 * Un solo proceso a la vez (bloqueo consultivo de PostgreSQL): el ciclo y el
 * final de una petición no escriben lo mismo dos veces.
 */
class ExportadorCambios
{
    private const BLOQUEO = 4204201;

    /** Tras tantos intentos fallidos, el cambio se deja de reintentar (lo muestra el estado). */
    public const MAX_INTENTOS = 20;

    public function __construct(
        private readonly Connection $db,
        private readonly EscritorLegado $escritor,
        private readonly ConexionLegado $legado,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return array{exportados: int, fallidos: int} */
    public function procesar(int $limite = 500): array
    {
        $resumen = ["exportados" => 0, "fallidos" => 0];
        if (!$this->db->fetchOne("SELECT pg_try_advisory_lock(:k)", ["k" => self::BLOQUEO])) {
            return $resumen;
        }
        try {
            $cambios = $this->db->fetchAllAssociative(
                "SELECT entidad, registro_id, array_agg(id) AS ids FROM sync_cambio
                  WHERE procesado_en IS NULL AND intentos < :max
                  GROUP BY entidad, registro_id ORDER BY MIN(id) LIMIT :n",
                ["max" => self::MAX_INTENTOS, "n" => $limite],
            );
            foreach ($cambios as $c) {
                $ids = array_map("intval", explode(",", trim((string) $c["ids"], "{}")));
                try {
                    $this->exportar((string) $c["entidad"], (int) $c["registro_id"]);
                    $this->db->executeStatement("UPDATE sync_cambio SET procesado_en = NOW(), error = NULL WHERE id IN (" . implode(",", $ids) . ")");
                    $resumen["exportados"]++;
                } catch (\Throwable $e) {
                    $this->legado->cerrar();
                    $this->logger->warning("Sincronización: no se llevó {entidad} {id} al legado: {error}", ["entidad" => $c["entidad"], "id" => $c["registro_id"], "error" => $e->getMessage()]);
                    $this->db->executeStatement(
                        "UPDATE sync_cambio SET intentos = intentos + 1, error = :error WHERE id IN (" . implode(",", $ids) . ")",
                        ["error" => mb_substr($e->getMessage(), 0, 2000)],
                    );
                    $resumen["fallidos"]++;
                }
            }
        } finally {
            $this->db->executeStatement("SELECT pg_advisory_unlock(:k)", ["k" => self::BLOQUEO]);
        }

        return $resumen;
    }

    private function exportar(string $entidad, int $id): void
    {
        match ($entidad) {
            "boleto" => $this->boleto($id),
            "boleto_anular" => $this->escritor->cambiarEstado((string) $id, Traduccion::estadoBoleto(EstadoBoletoAsiento::ANULADO), null, "Venta no confirmada en FDN."),
            "venta" => $this->escritor->facturaDeVenta($id),
            "encomienda" => $this->escritor->encomienda($id),
            "cliente" => $this->cliente($id),
            default => throw new \LogicException("Cambio desconocido: {$entidad}"),
        };
    }

    /** Estado, pasajero y asiento del boleto, como están ahora aquí. */
    private function boleto(int $id): void
    {
        $b = $this->db->fetchAssociative(
            "SELECT b.legacy_id, b.estado, b.cliente_id, a.numero FROM boleto_asiento b JOIN asiento a ON a.id = b.asiento_id WHERE b.id = :id",
            ["id" => $id],
        );
        if ($b === false || $b["legacy_id"] === null) {
            return;
        }
        $estado = EstadoBoletoAsiento::from((string) $b["estado"]);
        $this->escritor->cambiarEstado((string) $b["legacy_id"], Traduccion::estadoBoleto($estado), null, "Cambio en FDN: " . $estado->etiqueta() . ".");
        $this->escritor->pasajero((string) $b["legacy_id"], (int) $b["cliente_id"]);
    }

    /** Solo los que el legado conoce: los migrados y los que ya se llevaron allá. */
    private function cliente(int $id): void
    {
        $vinculado = $this->db->fetchOne("SELECT 1 FROM sync_vinculo WHERE entidad = 'cliente' AND id_nuevo = :id", ["id" => $id]) !== false;
        if (Identidades::esNuevo($id) && !$vinculado) {
            return;
        }
        $this->escritor->actualizarCliente($id);
    }
}
