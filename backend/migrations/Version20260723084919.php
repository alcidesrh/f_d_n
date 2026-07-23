<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723084919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action ADD ruta_params TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE action ADD icon_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE action DROP codigo');
        $this->addSql('ALTER TABLE action DROP recurso');
        $this->addSql('ALTER TABLE action DROP operacion');
        $this->addSql('ALTER TABLE action DROP grupo');
        $this->addSql('ALTER TABLE action ADD CONSTRAINT FK_47CC8C9254B9D732 FOREIGN KEY (icon_id) REFERENCES icon (id)');
        $this->addSql('CREATE INDEX IDX_47CC8C9254B9D732 ON action (icon_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action DROP CONSTRAINT FK_47CC8C9254B9D732');
        $this->addSql('DROP INDEX IDX_47CC8C9254B9D732');
        $this->addSql('ALTER TABLE action ADD codigo VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE action ADD recurso VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE action ADD operacion VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE action ADD grupo VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE action DROP ruta_params');
        $this->addSql('ALTER TABLE action DROP icon_id');
    }
}
