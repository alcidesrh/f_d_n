<?php

declare(strict_types=1);

namespace App\Ruta;

use Doctrine\DBAL\Connection;

/**
 * El catálogo de trayectos (pares comerciales origen→destino, ADR-030): el
 * de cada par de paradas en orden de una ruta, y uno suelto cuando hace falta
 * (una tarifa o un boleto del legado entre dos estaciones). Con DBAL para
 * servir igual a la app y a la migración. Único por par: nunca se duplica.
 */
final class Trayectos
{
    public function __construct(private readonly Connection $db) {}

    /** Cada par de paradas en orden de la ruta tiene su trayecto. */
    public function asegurarDeRuta(int $rutaId): void
    {
        $this->db->executeStatement(
            "INSERT INTO trayecto (origen_id, destino_id, activo)
             SELECT o.enclave_id, d.enclave_id, true
               FROM parada o JOIN parada d ON d.ruta_id = o.ruta_id AND d.orden > o.orden
              WHERE o.ruta_id = :r
             ON CONFLICT (origen_id, destino_id) DO NOTHING",
            ["r" => $rutaId],
        );
    }

    /** El trayecto de un par, creándolo si no existe. */
    public function par(int $origenId, int $destinoId): int
    {
        $this->db->executeStatement(
            "INSERT INTO trayecto (origen_id, destino_id, activo) VALUES (:o, :d, true) ON CONFLICT (origen_id, destino_id) DO NOTHING",
            ["o" => $origenId, "d" => $destinoId],
        );

        return (int) $this->db->fetchOne(
            "SELECT id FROM trayecto WHERE origen_id = :o AND destino_id = :d",
            ["o" => $origenId, "d" => $destinoId],
        );
    }
}
