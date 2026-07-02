<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630180503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entity_configuration ADD icon_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE entity_configuration ADD CONSTRAINT FK_844909DB54B9D732 FOREIGN KEY (icon_id) REFERENCES icon (id)');
        $this->addSql('CREATE INDEX IDX_844909DB54B9D732 ON entity_configuration (icon_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entity_configuration DROP CONSTRAINT FK_844909DB54B9D732');
        $this->addSql('DROP INDEX IDX_844909DB54B9D732');
        $this->addSql('ALTER TABLE entity_configuration DROP icon_id');
    }
}
