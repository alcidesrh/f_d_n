<?php

declare(strict_types=1);

namespace App\Geografia;

use Doctrine\DBAL\Connection;

/**
 * Copia regiones y ciudades del dataset de la página anterior (tablas
 * `states` y `cities`, ADR-041) depurándolas. Reemplaza todo el catálogo en
 * una transacción: nada lo referencia por clave foránea. Las regiones
 * conservan el id del origen.
 */
final class ImportadorGeografia
{
    private const LOTE = 1000;

    public function __construct(private readonly Connection $destino) {}

    /**
     * @return array{regiones: int, ciudades: int}
     */
    public function importar(Connection $origen): array
    {
        $porPais = [];
        $codigos = [];
        foreach ($origen->iterateAssociative("SELECT id, country_code, iso2, name FROM states") as $e) {
            $porPais[trim((string) $e["country_code"])][(int) $e["id"]] = (string) $e["name"];
            $codigo = trim((string) $e["iso2"]);
            $codigos[(int) $e["id"]] = $codigo !== "" ? mb_substr($codigo, 0, 10) : null;
        }
        $porRegion = [];
        foreach ($origen->iterateAssociative("SELECT state_id, name FROM cities") as $c) {
            $porRegion[(int) $c["state_id"]][] = (string) $c["name"];
        }

        return $this->destino->transactional(function (Connection $db) use ($porPais, $codigos, $porRegion) {
            $db->executeStatement("TRUNCATE ciudad, region");
            $regiones = [];
            foreach ($porPais as $pais => $nombres) {
                foreach (Depuracion::regiones($nombres) as $id => $nombre) {
                    $regiones[] = [$id, $pais, $codigos[$id], mb_substr($nombre, 0, 100)];
                }
            }
            $this->insertar($db, "region", ["id", "pais", "codigo", "nombre"], $regiones);
            $db->executeStatement("SELECT setval(pg_get_serial_sequence('region', 'id'), GREATEST((SELECT MAX(id) FROM region), 1))");

            $ciudades = [];
            foreach ($porRegion as $regionId => $nombres) {
                if (!array_key_exists($regionId, $codigos)) {
                    continue; // ciudad de una región que no vino
                }
                foreach (Depuracion::ciudades($nombres) as $nombre) {
                    $ciudades[] = [$regionId, mb_substr($nombre, 0, 100)];
                }
            }
            $this->insertar($db, "ciudad", ["region_id", "nombre"], $ciudades);

            return ["regiones" => count($regiones), "ciudades" => count($ciudades)];
        });
    }

    /**
     * @param list<string>      $columnas
     * @param list<list<mixed>> $filas
     */
    private function insertar(Connection $db, string $tabla, array $columnas, array $filas): void
    {
        $fila = "(" . implode(", ", array_fill(0, count($columnas), "?")) . ")";
        foreach (array_chunk($filas, self::LOTE) as $lote) {
            $db->executeStatement(
                sprintf("INSERT INTO %s (%s) VALUES %s", $tabla, implode(", ", $columnas), implode(", ", array_fill(0, count($lote), $fila))),
                array_merge(...$lote),
            );
        }
    }
}
