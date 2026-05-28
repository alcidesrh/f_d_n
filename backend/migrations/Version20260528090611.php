<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528090611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asiento ADD legacy_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE boleto ADD legacy_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE bus ADD legacy_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE cliente ADD legacy_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE empresa ADD legacy_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE enclave ADD legacy_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE salida ADD legacy_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE tarifa ADD legacy_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE trayecto ADD legacy_id VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asiento DROP legacy_id');
        $this->addSql('ALTER TABLE boleto DROP legacy_id');
        $this->addSql('ALTER TABLE bus DROP legacy_id');
        $this->addSql('ALTER TABLE cliente DROP legacy_id');
        $this->addSql('ALTER TABLE empresa DROP legacy_id');
        $this->addSql('ALTER TABLE enclave DROP legacy_id');
        $this->addSql('ALTER TABLE salida DROP legacy_id');
        $this->addSql('ALTER TABLE tarifa DROP legacy_id');
        $this->addSql('ALTER TABLE trayecto DROP legacy_id');
    }
}
