<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Listado de boletos (el "Boletos" del legado):
 *
 * - Acción `boleto.cancelar` (el pasajero no viaja) en el permiso
 *   "Anulacion y Reasignacion de Boletos".
 * - Ruta `/boletos` y el ítem "Buscar" del menú de boletos apuntando a ella
 *   (hasta ahora apuntaba a la portada); si no existe, se agrega.
 */
final class Version20261011220000 extends AbstractMigration
{
    private const PERMISO = 'Anulacion y Reasignacion de Boletos';

    public function getDescription(): string
    {
        return 'Acción boleto.cancelar y menú Boletos → /boletos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO permiso (nombre) SELECT :nombre WHERE NOT EXISTS (SELECT 1 FROM permiso WHERE nombre = :nombre)',
            ['nombre' => self::PERMISO],
        );
        $this->addSql("INSERT INTO action (codigo, nombre) SELECT 'boleto.cancelar', 'Cancelar boletos (no viaja)' WHERE NOT EXISTS (SELECT 1 FROM action WHERE codigo = 'boleto.cancelar')");
        $this->addSql(
            "INSERT INTO permiso_action (permiso_id, action_id)
             SELECT p.id, a.id FROM permiso p, action a WHERE p.nombre = :nombre AND a.codigo = 'boleto.cancelar'
             ON CONFLICT DO NOTHING",
            ['nombre' => self::PERMISO],
        );

        // La ruta de vue-router (el frontend la sincroniza al arrancar; aquí por si el menú se crea antes).
        $this->addSql("INSERT INTO vue_route (nombre, path, vue_route_name) SELECT 'boletos', '/boletos', 'boletos'
                       WHERE NOT EXISTS (SELECT 1 FROM vue_route WHERE vue_route_name = 'boletos' AND vue_route_id IS NULL)");
        // "Buscar" del menú de boletos, que apuntaba a la portada.
        $this->addSql("UPDATE menu_item SET route_id = (SELECT id FROM vue_route WHERE vue_route_name = 'boletos' AND vue_route_id IS NULL LIMIT 1)
                        WHERE id IN (SELECT n.subject_id FROM taxonomy_node n JOIN taxonomy t ON t.id = n.taxonomy_id
                                      WHERE n.subject_class = 'MenuItem' AND t.nombre = 'Menú: Boleto')
                          AND nombre = 'Buscar'
                          AND route_id = (SELECT id FROM vue_route WHERE path = '/' AND vue_route_id IS NULL LIMIT 1)");
        // Si no había, un ítem "Boletos" al final del menú de boletos.
        $this->addSql("INSERT INTO icon (icon, name) SELECT 'confirmation-number-outline', 'confirmation-number-outline' WHERE NOT EXISTS (SELECT 1 FROM icon WHERE icon = 'confirmation-number-outline')");
        $this->addSql("INSERT INTO menu_item (nombre, route_id, icon_id)
                       SELECT 'Boletos', (SELECT id FROM vue_route WHERE vue_route_name = 'boletos' AND vue_route_id IS NULL LIMIT 1), (SELECT id FROM icon WHERE icon = 'confirmation-number-outline' LIMIT 1)
                       WHERE NOT EXISTS (SELECT 1 FROM menu_item WHERE route_id = (SELECT id FROM vue_route WHERE vue_route_name = 'boletos' AND vue_route_id IS NULL LIMIT 1))");
        $this->addSql("INSERT INTO taxonomy_node (taxonomy_id, parent_id, position, subject_class, subject_id)
                       SELECT t.id, NULL, COALESCE((SELECT MAX(position) + 1 FROM taxonomy_node WHERE taxonomy_id = t.id AND parent_id IS NULL), 0),
                              'MenuItem', (SELECT id FROM menu_item WHERE nombre = 'Boletos' LIMIT 1)
                         FROM taxonomy t
                        WHERE t.nombre = 'Menú: Boleto'
                          AND EXISTS (SELECT 1 FROM menu_item WHERE nombre = 'Boletos')
                          AND NOT EXISTS (SELECT 1 FROM taxonomy_node x WHERE x.subject_class = 'MenuItem' AND x.subject_id = (SELECT id FROM menu_item WHERE nombre = 'Boletos' LIMIT 1))
                        LIMIT 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM taxonomy_node WHERE subject_class = 'MenuItem' AND subject_id = (SELECT id FROM menu_item WHERE nombre = 'Boletos')");
        $this->addSql("DELETE FROM menu_item WHERE nombre = 'Boletos'");
        $this->addSql("UPDATE menu_item SET route_id = (SELECT id FROM vue_route WHERE path = '/' AND vue_route_id IS NULL LIMIT 1)
                        WHERE route_id = (SELECT id FROM vue_route WHERE vue_route_name = 'boletos' AND vue_route_id IS NULL LIMIT 1)");
        $this->addSql("DELETE FROM permiso_action WHERE action_id = (SELECT id FROM action WHERE codigo = 'boleto.cancelar')");
        $this->addSql("DELETE FROM action WHERE codigo = 'boleto.cancelar'");
    }
}
