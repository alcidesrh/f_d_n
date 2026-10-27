<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `collection_field_config.editable`: la columna se edita en línea en el
 * listado. Nace apagada en todas: solo las que se marquen `true` en
 * `/configuracion/entidades/{entidad}` se editan desde la celda.
 */
final class Version20261027120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Edición en línea por columna del listado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_field_config ADD editable BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_field_config DROP editable');
    }
}
