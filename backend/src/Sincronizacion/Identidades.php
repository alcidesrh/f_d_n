<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use Doctrine\DBAL\Connection;

/**
 * Clientes y usuarios conservan el id del legado (la migración los inserta
 * con él). Los que se crean aquí, mientras el legado sigue creando los suyos,
 * toman ids de un rango que el legado no alcanza: si no, un cliente nuevo del
 * legado tendría el id de uno de aquí y la migración los confundiría.
 *
 * La migración deja la identidad en el mayor id + 1 al terminar; por eso se
 * vuelve a apartar tras cada importación.
 */
class Identidades
{
    /** Primer id de lo creado aquí durante la sincronización. */
    public const RANGO = 1_000_000_000;

    private const TABLAS = ["cliente", "usuario"];

    public function __construct(private readonly Connection $db) {}

    public function apartarRango(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->db->executeStatement(
                "SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), GREATEST(:rango, COALESCE((SELECT MAX(id) FROM {$tabla}), 0) + 1), false)",
                ["rango" => self::RANGO],
            );
        }
    }

    /** ¿El id lo dio este sistema (no viene del legado)? */
    public static function esNuevo(int $id): bool
    {
        return $id >= self::RANGO;
    }
}
