<?php

declare(strict_types=1);

namespace App\Geografia;

use Doctrine\DBAL\Connection;

/** Listas para la dirección de facturación de la página web (ADR-041). */
final class ConsultaGeografia
{
    public function __construct(private readonly Connection $db) {}

    /**
     * Regiones del país; vacío si no hay (el formulario pide texto libre).
     *
     * @return list<array{id: int, codigo: ?string, nombre: string}>
     */
    public function regiones(string $pais): array
    {
        $filas = $this->db->fetchAllAssociative(
            "SELECT id, codigo, nombre FROM region WHERE pais = ? ORDER BY nombre",
            [strtoupper($pais)],
        );

        return array_map(static fn(array $f) => [
            "id" => (int) $f["id"],
            "codigo" => $f["codigo"] !== null ? (string) $f["codigo"] : null,
            "nombre" => (string) $f["nombre"],
        ], $filas);
    }

    /**
     * Ciudades de la región; vacío si no hay (texto libre).
     *
     * @return list<string>
     */
    public function ciudades(int $region): array
    {
        return array_map(
            "strval",
            $this->db->fetchFirstColumn("SELECT nombre FROM ciudad WHERE region_id = ? ORDER BY nombre", [$region]),
        );
    }
}
