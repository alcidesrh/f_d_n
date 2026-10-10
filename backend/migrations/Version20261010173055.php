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
 * - `sortable`/`filterable` en null = "lo que permita la API". La
 *   sincronización con el mapeo los ponía en `false` en cada corrida, así que
 *   un `false` no era una decisión de nadie: pasan a null en las entidades
 *   donde ninguna columna se configuró a mano (alguna en `true`).
 */
final class Version20261010173055 extends AbstractMigration
{
    private const CONFIGURADAS_A_MANO = 'SELECT entity_config_id FROM collection_field_config WHERE sortable OR filterable';

    public function getDescription(): string
    {
        return 'Ancho de columna, opciones del listado y sortable/filterable automáticos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_field_config ADD width VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE entity_configuration ADD list_options JSON DEFAULT NULL');
        $this->addSql('UPDATE collection_field_config SET sortable = NULL WHERE sortable = false AND entity_config_id NOT IN ('.self::CONFIGURADAS_A_MANO.')');
        $this->addSql('UPDATE collection_field_config SET filterable = NULL WHERE filterable = false AND entity_config_id NOT IN ('.self::CONFIGURADAS_A_MANO.')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE collection_field_config SET sortable = false WHERE sortable IS NULL');
        $this->addSql('UPDATE collection_field_config SET filterable = false WHERE filterable IS NULL');
        $this->addSql('ALTER TABLE collection_field_config DROP width');
        $this->addSql('ALTER TABLE entity_configuration DROP list_options');
    }
}
