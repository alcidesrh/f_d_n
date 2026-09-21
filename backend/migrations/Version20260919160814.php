<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919160814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bus ADD piloto_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bus ADD copiloto_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bus ADD CONSTRAINT FK_2F566B699AAD4A8D FOREIGN KEY (piloto_id) REFERENCES piloto (id)');
        $this->addSql('ALTER TABLE bus ADD CONSTRAINT FK_2F566B69FEF9400B FOREIGN KEY (copiloto_id) REFERENCES piloto (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_2F566B699AAD4A8D ON bus (piloto_id)');
        $this->addSql('CREATE INDEX IDX_2F566B69FEF9400B ON bus (copiloto_id)');
        $this->addSql('ALTER TABLE recorrido DROP CONSTRAINT fk_3a1543a89aad4a8d');
        $this->addSql('DROP INDEX idx_3a1543a89aad4a8d');
        $this->addSql('ALTER TABLE recorrido DROP piloto_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bus DROP CONSTRAINT FK_2F566B699AAD4A8D');
        $this->addSql('ALTER TABLE bus DROP CONSTRAINT FK_2F566B69FEF9400B');
        $this->addSql('DROP INDEX IDX_2F566B699AAD4A8D');
        $this->addSql('DROP INDEX IDX_2F566B69FEF9400B');
        $this->addSql('ALTER TABLE bus DROP piloto_id');
        $this->addSql('ALTER TABLE bus DROP copiloto_id');
        $this->addSql('ALTER TABLE recorrido ADD piloto_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE recorrido ADD CONSTRAINT fk_3a1543a89aad4a8d FOREIGN KEY (piloto_id) REFERENCES piloto (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_3a1543a89aad4a8d ON recorrido (piloto_id)');
    }
}
