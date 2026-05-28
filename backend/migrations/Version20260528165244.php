<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528165244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE trayecto_trayecto (trayecto_source INT NOT NULL, trayecto_target INT NOT NULL, PRIMARY KEY (trayecto_source, trayecto_target))');
        $this->addSql('CREATE INDEX IDX_235C9B1BE769137 ON trayecto_trayecto (trayecto_source)');
        $this->addSql('CREATE INDEX IDX_235C9B1A793C1B8 ON trayecto_trayecto (trayecto_target)');
        $this->addSql('ALTER TABLE trayecto_trayecto ADD CONSTRAINT FK_235C9B1BE769137 FOREIGN KEY (trayecto_source) REFERENCES trayecto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trayecto_trayecto ADD CONSTRAINT FK_235C9B1A793C1B8 FOREIGN KEY (trayecto_target) REFERENCES trayecto (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trayecto DROP CONSTRAINT fk_d1926d33727aca70');
        $this->addSql('DROP INDEX idx_d1926d33727aca70');
        $this->addSql('ALTER TABLE trayecto DROP parent_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trayecto_trayecto DROP CONSTRAINT FK_235C9B1BE769137');
        $this->addSql('ALTER TABLE trayecto_trayecto DROP CONSTRAINT FK_235C9B1A793C1B8');
        $this->addSql('DROP TABLE trayecto_trayecto');
        $this->addSql('ALTER TABLE trayecto ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE trayecto ADD CONSTRAINT fk_d1926d33727aca70 FOREIGN KEY (parent_id) REFERENCES trayecto (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d1926d33727aca70 ON trayecto (parent_id)');
    }
}
