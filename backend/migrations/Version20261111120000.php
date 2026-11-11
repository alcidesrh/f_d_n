<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fuera la migración masiva del legado (ADR-056): ya se hizo y no se repite;
 * lo que cambia allá lo trae la sincronización (ADR-042). Con la pantalla
 * `/migracion` y la capacidad `migracion.ejecutar` se va lo que apuntaba a
 * ellas: su entrada en los menús, su enlace de administración, la ruta
 * sincronizada y la capacidad en los roles (un rol que guardara una capacidad
 * que ya no existe no se podría volver a editar).
 */
final class Version20261111120000 extends AbstractMigration
{
    private const ITEMS = "SELECT id FROM menu_item WHERE path = '/migracion' OR route_id IN (SELECT id FROM vue_route WHERE vue_route_name = 'migracion')";

    public function getDescription(): string
    {
        return 'Fuera la pantalla de migración del legado y la capacidad migracion.ejecutar (ADR-056)';
    }

    public function up(Schema $schema): void
    {
        // Lo que colgaba de la entrada en un menú sube un nivel: borrar un nodo borra a sus hijos.
        $this->addSql(
            "UPDATE taxonomy_node h SET parent_id = n.parent_id FROM taxonomy_node n
              WHERE h.parent_id = n.id AND n.subject_class = 'MenuItem' AND n.subject_id IN (" . self::ITEMS . ')',
        );
        $this->addSql("DELETE FROM taxonomy_node WHERE subject_class = 'MenuItem' AND subject_id IN (" . self::ITEMS . ')');
        $this->addSql("DELETE FROM menu_item WHERE path = '/migracion' OR route_id IN (SELECT id FROM vue_route WHERE vue_route_name = 'migracion')");
        $this->addSql("DELETE FROM vue_route WHERE vue_route_name = 'migracion'");
        $this->addSql("DELETE FROM enlace_administracion WHERE ruta = 'migracion' OR path = '/migracion'");
        $this->addSql("UPDATE enlace_administracion SET agregar_ruta = NULL, agregar_parametros = NULL, agregar_path = NULL WHERE agregar_ruta = 'migracion' OR agregar_path = '/migracion'");

        $this->addSql(
            "UPDATE role SET capacidades = COALESCE(
                 (SELECT json_agg(c) FROM json_array_elements_text(capacidades) AS c WHERE c <> 'migracion.ejecutar'),
                 '[]'::json)
             WHERE capacidades::text LIKE '%\"migracion.ejecutar\"%'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La pantalla de migración y su capacidad ya no existen en el código.');
    }
}
