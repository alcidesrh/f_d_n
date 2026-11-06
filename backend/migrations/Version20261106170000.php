<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ítems de menú sin ícono: quita el NOT NULL de `icon_id`. Los ítems
 * existentes conservan su ícono; el menú muestra el de por defecto cuando
 * un ítem no tiene.
 */
final class Version20261106170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Menú: ícono opcional en los ítems';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item ALTER COLUMN icon_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM taxonomy_node WHERE subject_class = \'MenuItem\' AND subject_id IN (SELECT id FROM menu_item WHERE icon_id IS NULL)');
        $this->addSql('DELETE FROM menu_item WHERE icon_id IS NULL');
        $this->addSql('ALTER TABLE menu_item ALTER COLUMN icon_id SET NOT NULL');
    }
}
