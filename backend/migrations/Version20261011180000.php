<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Abordaje (ADR-032):
 *
 * - Estado `cancelado` del boleto (no se presentó a abordar): no ocupa el
 *   asiento, así que sale de `ex_boleto_asiento_tramo` como anulado y
 *   reasignado.
 * - Acciones `boleto.chequear`, `salida.abordar` y `salida.iniciar` en el
 *   permiso "Abordaje de Salidas".
 * - Ítem "Chequear boletos" (`/salidas/chequeo`) en el menú de salidas.
 *
 * Los boletos que ya se migraron como anulados y en el legado son
 * cancelados se corrigen con `bin/console app:venta:cancelados-legado`.
 */
final class Version20261011180000 extends AbstractMigration
{
    private const PERMISO = 'Abordaje de Salidas';

    private const ACCIONES = [
        'boleto.chequear' => 'Chequear boletos al abordar',
        'salida.abordar' => 'Poner salidas en abordaje',
        'salida.iniciar' => 'Iniciar salidas',
    ];

    public function getDescription(): string
    {
        return 'Boleto cancelado (no se presentó), acciones de abordaje y menú Chequear boletos (ADR-032)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boleto_asiento DROP CONSTRAINT IF EXISTS ex_boleto_asiento_tramo');
        $this->addSql("ALTER TABLE boleto_asiento ADD CONSTRAINT ex_boleto_asiento_tramo EXCLUDE USING gist (salida_id WITH =, asiento_id WITH =, int4range(orden_sube, orden_baja) WITH &&) WHERE (estado NOT IN ('anulado', 'reasignado', 'cancelado'))");

        $this->addSql(
            'INSERT INTO permiso (nombre) SELECT :nombre WHERE NOT EXISTS (SELECT 1 FROM permiso WHERE nombre = :nombre)',
            ['nombre' => self::PERMISO],
        );
        foreach (self::ACCIONES as $codigo => $nombre) {
            $this->addSql(
                'INSERT INTO action (codigo, nombre) SELECT :codigo, :nombre WHERE NOT EXISTS (SELECT 1 FROM action WHERE codigo = :codigo)',
                ['codigo' => $codigo, 'nombre' => $nombre],
            );
            $this->addSql(
                'INSERT INTO permiso_action (permiso_id, action_id)
                 SELECT p.id, a.id FROM permiso p, action a WHERE p.nombre = :nombre AND a.codigo = :codigo
                 ON CONFLICT DO NOTHING',
                ['nombre' => self::PERMISO, 'codigo' => $codigo],
            );
        }

        // La ruta de vue-router (el frontend la sincroniza al arrancar; aquí por si el menú se crea antes).
        $this->addSql("INSERT INTO vue_route (nombre, path, vue_route_name) SELECT 'salidas-chequeo', '/salidas/chequeo', 'salidas-chequeo'
                       WHERE NOT EXISTS (SELECT 1 FROM vue_route WHERE vue_route_name = 'salidas-chequeo' AND vue_route_id IS NULL)");
        $this->addSql("INSERT INTO icon (icon, name) SELECT 'how-to-reg-outline', 'how-to-reg-outline' WHERE NOT EXISTS (SELECT 1 FROM icon WHERE icon = 'how-to-reg-outline')");
        $this->addSql("INSERT INTO menu_item (nombre, route_id, icon_id)
                       SELECT 'Chequear boletos', (SELECT id FROM vue_route WHERE vue_route_name = 'salidas-chequeo' AND vue_route_id IS NULL LIMIT 1), (SELECT id FROM icon WHERE icon = 'how-to-reg-outline' LIMIT 1)
                       WHERE NOT EXISTS (SELECT 1 FROM menu_item WHERE nombre = 'Chequear boletos')");
        // Al final del menú que tiene "Programar" (salidas), si existe.
        $this->addSql("INSERT INTO taxonomy_node (taxonomy_id, parent_id, position, subject_class, subject_id)
                       SELECT n.taxonomy_id, n.parent_id, (SELECT MAX(position) + 1 FROM taxonomy_node WHERE taxonomy_id = n.taxonomy_id AND parent_id IS NOT DISTINCT FROM n.parent_id),
                              'MenuItem', (SELECT id FROM menu_item WHERE nombre = 'Chequear boletos')
                         FROM taxonomy_node n
                        WHERE n.subject_class = 'MenuItem' AND n.subject_id = (SELECT id FROM menu_item mi WHERE mi.route_id = (SELECT id FROM vue_route WHERE path = '/salidas/programar' LIMIT 1) LIMIT 1)
                          AND NOT EXISTS (SELECT 1 FROM taxonomy_node x WHERE x.subject_class = 'MenuItem' AND x.subject_id = (SELECT id FROM menu_item WHERE nombre = 'Chequear boletos'))
                        LIMIT 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM taxonomy_node WHERE subject_class = 'MenuItem' AND subject_id = (SELECT id FROM menu_item WHERE nombre = 'Chequear boletos')");
        $this->addSql("DELETE FROM menu_item WHERE nombre = 'Chequear boletos'");
        foreach (array_keys(self::ACCIONES) as $codigo) {
            $this->addSql('DELETE FROM permiso_action WHERE action_id = (SELECT id FROM action WHERE codigo = :codigo)', ['codigo' => $codigo]);
            $this->addSql('DELETE FROM action WHERE codigo = :codigo', ['codigo' => $codigo]);
        }
        $this->addSql('DELETE FROM permiso WHERE nombre = :nombre', ['nombre' => self::PERMISO]);
        $this->addSql("UPDATE boleto_asiento SET estado = 'anulado' WHERE estado = 'cancelado'");
        $this->addSql('ALTER TABLE boleto_asiento DROP CONSTRAINT IF EXISTS ex_boleto_asiento_tramo');
        $this->addSql("ALTER TABLE boleto_asiento ADD CONSTRAINT ex_boleto_asiento_tramo EXCLUDE USING gist (salida_id WITH =, asiento_id WITH =, int4range(orden_sube, orden_baja) WITH &&) WHERE (estado NOT IN ('anulado', 'reasignado'))");
    }
}
