<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007090143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Salida.created_by_id (nulo en las migradas) y created_at/updated_at de las migradas sin fecha';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE salida ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE salida ADD CONSTRAINT FK_95F4C748B03A8386 FOREIGN KEY (created_by_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_95F4C748B03A8386 ON salida (created_by_id)');
        // El migrador insertaba por SQL directo, sin los timestamps de Gedmo.
        $this->addSql('UPDATE salida SET created_at = COALESCE(created_at, NOW()), updated_at = COALESCE(updated_at, NOW()) WHERE created_at IS NULL OR updated_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE salida DROP CONSTRAINT FK_95F4C748B03A8386');
        $this->addSql('DROP INDEX IDX_95F4C748B03A8386');
        $this->addSql('ALTER TABLE salida DROP created_by_id');
    }
}
