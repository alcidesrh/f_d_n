<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `collection_field_config.autocomplete`: la columna es una relación a una
 * entidad con muchos registros y su filtro busca en el servidor mientras se
 * teclea, sin cargar la lista completa. Se enciende en las columnas
 * `trayecto` (miles de registros).
 */
final class Version20261015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Filtro con autocompletado asincrónico en columnas de relación';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_field_config ADD autocomplete BOOLEAN DEFAULT NULL');
        $this->addSql("UPDATE collection_field_config SET autocomplete = TRUE WHERE field = 'trayecto'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_field_config DROP autocomplete');
    }
}
