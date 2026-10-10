<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Listado propio sobre CSS Grid (ADR-029):
 *
 * - `collection_field_config.width`: ancho de la columna (longitud CSS o preset).
 * - `entity_configuration.list_options`: opciones del listado de la entidad
 *   (filas por página, densidad, combinación de filtros, selección, edición).
 *
 * `sortable`/`filterable` siguen apagados por defecto (se habilitan a mano);
 * la sincronización ya no los pisa (`CollectionFieldConfig::setData`).
 */
final class Version20261010173055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ancho de columna y opciones del listado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_field_config ADD width VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE entity_configuration ADD list_options JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_field_config DROP width');
        $this->addSql('ALTER TABLE entity_configuration DROP list_options');
    }
}
