<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Menú "Reporte": Boleto Manifiesto Interno, después de los
 * que ya tiene. Idempotente: no duplica rutas ni ítems si ya existen.
 */
final class Version20261013120000 extends AbstractMigration
{
    private const TAXONOMIA = 'Menú: Reporte';

    /** [nombre del ítem, vue_route_name, path, ícono] en orden. */
    private const ITEMS = [
        ['Boleto Manifiesto Interno', 'reporte-boleto-manifiesto-interno', '/reporte/boleto-manifiesto-interno', 'assignment-outline'],
    ];

    public function getDescription(): string
    {
        return 'Menú Reporte: Boleto Manifiesto Interno';
    }

    public function up(Schema $schema): void
    {
        foreach (self::ITEMS as [$nombre, $ruta, $path, $icono]) {
            $this->addSql('INSERT INTO icon (icon, name) SELECT :i, :i WHERE NOT EXISTS (SELECT 1 FROM icon WHERE icon = :i)', ['i' => $icono]);
            $this->addSql(
                'INSERT INTO vue_route (nombre, path, vue_route_name) SELECT :r, :p, :r
                  WHERE NOT EXISTS (SELECT 1 FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL)',
                ['r' => $ruta, 'p' => $path],
            );
            $this->addSql(
                'INSERT INTO menu_item (nombre, route_id, icon_id)
                 SELECT :n, (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL LIMIT 1), (SELECT id FROM icon WHERE icon = :i LIMIT 1)
                  WHERE NOT EXISTS (SELECT 1 FROM menu_item WHERE route_id = (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL LIMIT 1))',
                ['n' => $nombre, 'r' => $ruta, 'i' => $icono],
            );
            $this->addSql(
                "INSERT INTO taxonomy_node (taxonomy_id, parent_id, position, subject_class, subject_id)
                 SELECT t.id, NULL, COALESCE((SELECT MAX(x.position) + 1 FROM taxonomy_node x WHERE x.taxonomy_id = t.id AND x.parent_id IS NULL), 0), 'MenuItem', mi.id
                   FROM taxonomy t, menu_item mi
                  WHERE t.nombre = :t
                    AND mi.id = (SELECT id FROM menu_item WHERE route_id = (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL LIMIT 1) LIMIT 1)
                    AND NOT EXISTS (SELECT 1 FROM taxonomy_node x WHERE x.taxonomy_id = t.id AND x.subject_class = 'MenuItem' AND x.subject_id = mi.id)",
                ['t' => self::TAXONOMIA, 'r' => $ruta],
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::ITEMS as [, $ruta]) {
            $this->addSql("DELETE FROM taxonomy_node WHERE subject_class = 'MenuItem' AND subject_id IN (SELECT id FROM menu_item WHERE route_id IN (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL))", ['r' => $ruta]);
            $this->addSql('DELETE FROM menu_item WHERE route_id IN (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL)', ['r' => $ruta]);
        }
    }
}
