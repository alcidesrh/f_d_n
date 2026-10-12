<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Menú "Agencia" (ADR-034), como el del legado: Registrar depósito, Buscar
 * depósito y Consultar saldos, en el sidebar izquierdo después de los demás.
 * Visible para el superadministrador y los roles del legado que lo tenían.
 * Idempotente: no duplica rutas, ítems ni el menú si ya existen.
 */
final class Version20261012130000 extends AbstractMigration
{
    private const TAXONOMIA = 'Menú: Agencia';

    /** [nombre del ítem, vue_route_name, path, ícono] en orden. */
    private const ITEMS = [
        ['Registrar depósito', 'agencia-depositos-registrar', '/agencias/depositos/registrar', 'savings-outline'],
        ['Buscar depósito', 'agencia-depositos', '/agencias/depositos', 'account-balance-outline'],
        ['Consultar saldos', 'agencia-saldos', '/agencias/saldos', 'account-balance-wallet-outline'],
    ];

    private const ROLES = ['ROLE_SUPER_ADMIN', 'ROLE_AGENCIA', 'ROLE_SUPERVISOR_AGENCIA', 'ROLE_PROPIETARIO', 'ROLE_ADMINISTRATIVOS'];

    public function getDescription(): string
    {
        return 'Menú Agencia: registrar, buscar depósito y consultar saldos (ADR-034)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO taxonomy (nombre, subject_class) SELECT :t, 'MenuItem' WHERE NOT EXISTS (SELECT 1 FROM taxonomy WHERE nombre = :t)", ['t' => self::TAXONOMIA]);
        $this->addSql("INSERT INTO menu (nombre, taxonomy_id) SELECT 'Agencia', t.id FROM taxonomy t WHERE t.nombre = :t AND NOT EXISTS (SELECT 1 FROM menu m WHERE m.taxonomy_id = t.id)", ['t' => self::TAXONOMIA]);
        $this->addSql(
            "INSERT INTO menu_placement (area, position, menu_id)
             SELECT 'sidebar_left', COALESCE((SELECT MAX(position) + 1 FROM menu_placement WHERE area = 'sidebar_left'), 0), m.id
               FROM menu m JOIN taxonomy t ON t.id = m.taxonomy_id
              WHERE t.nombre = :t AND NOT EXISTS (SELECT 1 FROM menu_placement p WHERE p.menu_id = m.id)",
            ['t' => self::TAXONOMIA],
        );
        foreach (self::ROLES as $rol) {
            $this->addSql(
                'INSERT INTO menu_role (menu_id, role_id)
                 SELECT m.id, r.id FROM menu m JOIN taxonomy t ON t.id = m.taxonomy_id, role r
                  WHERE t.nombre = :t AND r.nombre = :r
                 ON CONFLICT DO NOTHING',
                ['t' => self::TAXONOMIA, 'r' => $rol],
            );
        }

        foreach (self::ITEMS as $i => [$nombre, $ruta, $path, $icono]) {
            $this->addSql('INSERT INTO icon (icon, name) SELECT :i, :i WHERE NOT EXISTS (SELECT 1 FROM icon WHERE icon = :i)', ['i' => $icono]);
            // La ruta de vue-router (el frontend la sincroniza al arrancar; aquí por si el menú se crea antes).
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
                 SELECT t.id, NULL, :pos, 'MenuItem', mi.id
                   FROM taxonomy t, menu_item mi
                  WHERE t.nombre = :t
                    AND mi.id = (SELECT id FROM menu_item WHERE route_id = (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL LIMIT 1) LIMIT 1)
                    AND NOT EXISTS (SELECT 1 FROM taxonomy_node x WHERE x.taxonomy_id = t.id AND x.subject_class = 'MenuItem' AND x.subject_id = mi.id)",
                ['t' => self::TAXONOMIA, 'r' => $ruta, 'pos' => $i],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM taxonomy_node WHERE taxonomy_id = (SELECT id FROM taxonomy WHERE nombre = :t)', ['t' => self::TAXONOMIA]);
        $this->addSql('DELETE FROM menu WHERE taxonomy_id = (SELECT id FROM taxonomy WHERE nombre = :t)', ['t' => self::TAXONOMIA]);
        $this->addSql('DELETE FROM taxonomy WHERE nombre = :t', ['t' => self::TAXONOMIA]);
        foreach (self::ITEMS as [, $ruta]) {
            $this->addSql('DELETE FROM menu_item WHERE route_id IN (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL)', ['r' => $ruta]);
        }
    }
}
