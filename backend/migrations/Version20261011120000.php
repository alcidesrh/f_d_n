<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gestión de rutas (ADR-030, fase 6): acción `ruta.editar` en el permiso
 * "Gestion Salidas" y el ítem "Rutas" (`/rutas`) en el menú de salidas.
 */
final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Acción ruta.editar y menú Rutas (ADR-030, fase 6)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO action (codigo, nombre) SELECT 'ruta.editar', 'Editar rutas' WHERE NOT EXISTS (SELECT 1 FROM action WHERE codigo = 'ruta.editar')");
        $this->addSql("INSERT INTO permiso_action (permiso_id, action_id)
                       SELECT p.id, a.id FROM permiso p, action a WHERE p.nombre = 'Gestion Salidas' AND a.codigo = 'ruta.editar'
                       ON CONFLICT DO NOTHING");

        // La ruta de vue-router (el frontend la sincroniza al arrancar; aquí por si el menú se crea antes).
        $this->addSql("INSERT INTO vue_route (nombre, path, vue_route_name) SELECT 'rutas', '/rutas', 'rutas'
                       WHERE NOT EXISTS (SELECT 1 FROM vue_route WHERE vue_route_name = 'rutas' AND vue_route_id IS NULL)");
        $this->addSql("INSERT INTO icon (icon, name) SELECT 'route', 'route' WHERE NOT EXISTS (SELECT 1 FROM icon WHERE icon = 'route')");
        $this->addSql("INSERT INTO menu_item (nombre, route_id, icon_id)
                       SELECT 'Rutas', (SELECT id FROM vue_route WHERE vue_route_name = 'rutas' AND vue_route_id IS NULL LIMIT 1), (SELECT id FROM icon WHERE icon = 'route' LIMIT 1)
                       WHERE NOT EXISTS (SELECT 1 FROM menu_item WHERE nombre = 'Rutas')");
        // Al final del menú que tiene "Programar" (salidas), si existe.
        $this->addSql("INSERT INTO taxonomy_node (taxonomy_id, parent_id, position, subject_class, subject_id)
                       SELECT n.taxonomy_id, n.parent_id, (SELECT MAX(position) + 1 FROM taxonomy_node WHERE taxonomy_id = n.taxonomy_id AND parent_id IS NOT DISTINCT FROM n.parent_id),
                              'MenuItem', (SELECT id FROM menu_item WHERE nombre = 'Rutas')
                         FROM taxonomy_node n
                        WHERE n.subject_class = 'MenuItem' AND n.subject_id = (SELECT id FROM menu_item mi WHERE mi.route_id = (SELECT id FROM vue_route WHERE path = '/salidas/programar' LIMIT 1) LIMIT 1)
                          AND NOT EXISTS (SELECT 1 FROM taxonomy_node x WHERE x.subject_class = 'MenuItem' AND x.subject_id = (SELECT id FROM menu_item WHERE nombre = 'Rutas'))
                        LIMIT 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM taxonomy_node WHERE subject_class = 'MenuItem' AND subject_id = (SELECT id FROM menu_item WHERE nombre = 'Rutas')");
        $this->addSql("DELETE FROM menu_item WHERE nombre = 'Rutas'");
        $this->addSql("DELETE FROM permiso_action WHERE action_id = (SELECT id FROM action WHERE codigo = 'ruta.editar')");
        $this->addSql("DELETE FROM action WHERE codigo = 'ruta.editar'");
    }
}
