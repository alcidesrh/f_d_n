<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Encomiendas, segunda parte (ADR-035):
 *
 * - Acciones `encomienda.especiales` (catálogo de especiales) y
 *   `reporte.encomiendas` (reportes de caja de encomiendas), con el permiso
 *   «Catalogo de Encomiendas» para ROLE_ENCOMIENDA_REGISTRAR_ESPECIAL.
 * - Menú «Encomienda»: Entregar, Pendientes de envío y Especiales, en el
 *   orden del legado.
 * - Menú «Reportes»: los seis reportes de encomiendas.
 *
 * Idempotente. Si hay dos taxonomías con el mismo nombre, usa la primera.
 */
final class Version20261019120000 extends AbstractMigration
{
    private const ACCIONES = [
        ['encomienda.especiales', 'Administrar el catálogo de encomiendas especiales'],
        ['reporte.encomiendas', 'Reportes de caja de encomiendas'],
    ];

    /** Permiso => acciones que se le suman. */
    private const PERMISOS = [
        'Catalogo de Encomiendas' => ['encomienda.ver', 'encomienda.especiales'],
        'Supervision de Encomiendas' => ['encomienda.especiales', 'reporte.encomiendas'],
        'Gestion Reportes' => ['reporte.encomiendas'],
    ];

    private const ROLES = [
        'ROLE_ENCOMIENDA_REGISTRAR_ESPECIAL' => 'Catalogo de Encomiendas',
    ];

    /** Taxonomía => [nombre del ítem, vue_route_name, path, ícono, posición] */
    private const ITEMS = [
        'Menú: Encomienda' => [
            ['Registrar', 'encomienda-registrar', '/encomiendas/registrar', 'package-2-outline', 0],
            ['Entregar', 'encomienda-entregar', '/encomiendas/entregar', 'handshake-outline', 1],
            ['Buscar', 'encomiendas', '/encomiendas', 'inventory-2-outline', 2],
            ['Procesar por salida', 'encomienda-salida', '/encomiendas/salida', 'local-shipping-outline', 3],
            ['Pendientes de envío', 'encomienda-pendientes-envio', '/encomiendas/pendientes-envio', 'pending-actions', 4],
            ['Especiales', 'encomienda-especiales', '/encomiendas/especiales', 'category-outline', 5],
        ],
        'Menú: Reportes' => [
            ['Encomienda Manifiesto', 'reporte-encomienda-manifiesto', '/reporte/encomienda-manifiesto', 'local-shipping-outline', null],
            ['Encomienda Cuadre', 'reporte-encomienda-cuadre', '/reporte/encomienda-cuadre', 'request-quote-outline', null],
            ['Encomienda Detalle Factura', 'reporte-encomienda-detalle-factura', '/reporte/encomienda-detalle-factura', 'receipt-long-outline', null],
            ['Encomienda Detalle General', 'reporte-encomienda-detalle-general', '/reporte/encomienda-detalle-general', 'list-alt-outline', null],
            ['Encomienda Guía Interna', 'reporte-encomienda-guia-interna', '/reporte/encomienda-guia-interna', 'assignment-ind-outline', null],
            ['Encomienda Pendientes de Entrega', 'reporte-encomienda-pendientes-entrega', '/reporte/encomienda-pendientes-entrega', 'pending-actions', null],
        ],
    ];

    public function getDescription(): string
    {
        return 'Encomiendas: entregar, pendientes de envío, especiales y reportes (ADR-035)';
    }

    public function up(Schema $schema): void
    {
        foreach (self::ACCIONES as [$codigo, $nombre]) {
            $this->addSql('INSERT INTO action (codigo, nombre) SELECT :c, :n WHERE NOT EXISTS (SELECT 1 FROM action WHERE codigo = :c)', ['c' => $codigo, 'n' => $nombre]);
        }
        foreach (self::PERMISOS as $permiso => $acciones) {
            $this->addSql('INSERT INTO permiso (nombre) SELECT :p WHERE NOT EXISTS (SELECT 1 FROM permiso WHERE nombre = :p)', ['p' => $permiso]);
            foreach ($acciones as $codigo) {
                $this->addSql(
                    'INSERT INTO permiso_action (permiso_id, action_id)
                     SELECT p.id, a.id FROM permiso p, action a WHERE p.nombre = :p AND a.codigo = :c
                     ON CONFLICT DO NOTHING',
                    ['p' => $permiso, 'c' => $codigo],
                );
            }
        }
        foreach (self::ROLES as $rol => $permiso) {
            $this->addSql(
                'INSERT INTO role_permiso (role_id, permiso_id)
                 SELECT r.id, p.id FROM role r, permiso p WHERE r.nombre = :r AND p.nombre = :p
                 ON CONFLICT DO NOTHING',
                ['r' => $rol, 'p' => $permiso],
            );
        }

        foreach (self::ITEMS as $taxonomia => $items) {
            foreach ($items as [$nombre, $ruta, $path, $icono, $posicion]) {
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
                $item = '(SELECT id FROM menu_item WHERE route_id = (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL LIMIT 1) LIMIT 1)';
                $taxo = '(SELECT MIN(id) FROM taxonomy WHERE nombre = :t)';
                $this->addSql(
                    "INSERT INTO taxonomy_node (taxonomy_id, parent_id, position, subject_class, subject_id)
                     SELECT {$taxo}, NULL, COALESCE(CAST(:pos AS INT), (SELECT MAX(x.position) + 1 FROM taxonomy_node x WHERE x.taxonomy_id = {$taxo} AND x.parent_id IS NULL), 0), 'MenuItem', {$item}
                      WHERE {$taxo} IS NOT NULL AND {$item} IS NOT NULL
                        AND NOT EXISTS (SELECT 1 FROM taxonomy_node x WHERE x.taxonomy_id = {$taxo} AND x.subject_class = 'MenuItem' AND x.subject_id = {$item})",
                    ['t' => $taxonomia, 'r' => $ruta, 'pos' => $posicion],
                );
                if ($posicion !== null) {
                    // Los que ya estaban toman el orden del legado.
                    $this->addSql(
                        "UPDATE taxonomy_node SET position = CAST(:pos AS INT)
                          WHERE taxonomy_id = {$taxo} AND parent_id IS NULL AND subject_class = 'MenuItem' AND subject_id = {$item}",
                        ['t' => $taxonomia, 'r' => $ruta, 'pos' => $posicion],
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::ITEMS as $items) {
            foreach ($items as [, $ruta, , , $posicion]) {
                if (in_array($ruta, ['encomienda-registrar', 'encomiendas', 'encomienda-salida'], true)) {
                    continue;
                }
                $this->addSql("DELETE FROM taxonomy_node WHERE subject_class = 'MenuItem' AND subject_id IN (SELECT id FROM menu_item WHERE route_id IN (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL))", ['r' => $ruta]);
                $this->addSql('DELETE FROM menu_item WHERE route_id IN (SELECT id FROM vue_route WHERE vue_route_name = :r AND vue_route_id IS NULL)', ['r' => $ruta]);
            }
        }
        foreach (self::ROLES as $rol => $permiso) {
            $this->addSql('DELETE FROM role_permiso WHERE role_id = (SELECT id FROM role WHERE nombre = :r) AND permiso_id = (SELECT id FROM permiso WHERE nombre = :p)', ['r' => $rol, 'p' => $permiso]);
        }
        foreach (self::ACCIONES as [$codigo]) {
            $this->addSql('DELETE FROM permiso_action WHERE action_id = (SELECT id FROM action WHERE codigo = :c)', ['c' => $codigo]);
            $this->addSql('DELETE FROM action WHERE codigo = :c', ['c' => $codigo]);
        }
        $this->addSql("DELETE FROM permiso WHERE nombre = 'Catalogo de Encomiendas'");
    }
}
