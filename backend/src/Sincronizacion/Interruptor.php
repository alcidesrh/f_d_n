<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Sincronizacion\Legado\ConexionLegado;
use Doctrine\DBAL\Connection;

/**
 * El interruptor de la sincronización (ADR-042). Vive en `sync_estado` y se
 * copia a `sync_config` del legado, que es lo que lee su bundle: un solo
 * interruptor para los dos sistemas.
 *
 * Al encenderlo, el legado tiene que responder (si no, el legado seguiría
 * vendiendo sin avisar). Al apagarlo se apaga aquí aunque el legado no
 * responda, y se avisa.
 *
 * En modo «solo traer» (`SINCRONIZACION_SOLO_TRAER`) el legado queda siempre
 * apagado: su bundle no llama aquí y aquí nada se escribe allá.
 */
class Interruptor
{
    /** La tabla existe (se recuerda solo el sí: el worker vive entre peticiones). */
    private ?bool $hayTabla = null;

    public function __construct(
        private readonly Connection $db,
        private readonly ConexionLegado $legado,
        private readonly Identidades $identidades,
    ) {}

    /** Sin la tabla (base sin la migración, como la de las pruebas), apagada. */
    public function activo(): bool
    {
        // Sin provocar un error: dentro de una transacción de PostgreSQL la dejaría inservible.
        $this->hayTabla ??= (bool) $this->db->fetchOne("SELECT to_regclass('sync_estado') IS NOT NULL");
        if (!$this->hayTabla) {
            $this->hayTabla = null;

            return false;
        }

        return (bool) $this->db->fetchOne("SELECT activo FROM sync_estado WHERE id = 1");
    }

    public function soloTraer(): bool
    {
        return $this->legado->soloTraer;
    }

    /**
     * @return array{activo: bool, soloTraer: bool, cambiadoEn: ?string, cambiadoPor: ?string, ultimoCicloEn: ?string, ultimoCiclo: ?array<string, mixed>, ultimoError: ?string, pendientes: int}
     */
    public function estado(): array
    {
        $fila = $this->db->fetchAssociative("SELECT * FROM sync_estado WHERE id = 1") ?: [];

        return [
            "activo" => (bool) ($fila["activo"] ?? false),
            "soloTraer" => $this->legado->soloTraer,
            "cambiadoEn" => self::iso($fila["cambiado_en"] ?? null),
            "cambiadoPor" => $fila["cambiado_por"] ?? null,
            "ultimoCicloEn" => self::iso($fila["ultimo_ciclo_en"] ?? null),
            "ultimoCiclo" => isset($fila["ultimo_ciclo"]) ? json_decode((string) $fila["ultimo_ciclo"], true) : null,
            "ultimoError" => $fila["ultimo_error"] ?? null,
            "pendientes" => (int) $this->db->fetchOne("SELECT COUNT(*) FROM sync_cambio WHERE procesado_en IS NULL"),
        ];
    }

    /**
     * @return ?string aviso para quien lo cambió (el legado no respondió al apagar)
     *
     * @throws \RuntimeException al encender si el legado no responde
     */
    public function cambiar(bool $activo, string $quien): ?string
    {
        $aviso = null;
        try {
            $this->legado->ejecutar(
                "UPDATE sync_config SET activo = :activo, cambiado_en = GETDATE() WHERE id = 1",
                ["activo" => $activo && !$this->legado->soloTraer],
            );
        } catch (\Throwable $e) {
            $this->legado->cerrar();
            if ($activo) {
                throw new \RuntimeException("No se pudo encender en el legado: " . $e->getMessage(), 0, $e);
            }
            $aviso = "Apagada aquí, pero el legado no respondió: sigue encendida allá hasta que responda (" . $e->getMessage() . ").";
        }
        if ($activo) {
            $this->identidades->apartarRango();
        }
        $this->db->executeStatement(
            "UPDATE sync_estado SET activo = :activo, cambiado_en = NOW(), cambiado_por = :quien WHERE id = 1",
            ["activo" => $activo ? "true" : "false", "quien" => mb_substr($quien, 0, 180)],
        );

        return $aviso;
    }

    /** @param array<string, mixed> $resumen */
    public function anotarCiclo(array $resumen, ?string $error): void
    {
        $this->db->executeStatement(
            "UPDATE sync_estado SET ultimo_ciclo_en = NOW(), ultimo_ciclo = :resumen, ultimo_error = :error WHERE id = 1",
            ["resumen" => json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "error" => $error],
        );
    }

    private static function iso(?string $fecha): ?string
    {
        return $fecha === null ? null : (new \DateTimeImmutable($fecha))->format(\DATE_ATOM);
    }
}
