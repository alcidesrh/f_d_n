<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630223834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE icon ADD codepoint INT DEFAULT NULL');
        $this->addSql('ALTER TABLE icon ADD popularity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE icon ADD tags JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE icon RENAME COLUMN icon TO codigo');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE icon DROP codepoint');
        $this->addSql('ALTER TABLE icon DROP popularity');
        $this->addSql('ALTER TABLE icon DROP tags');
        $this->addSql('ALTER TABLE icon RENAME COLUMN codigo TO icon');
    }
}
