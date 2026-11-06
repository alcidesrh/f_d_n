<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cliente comodín: el de quien no da sus datos en taquilla';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cliente ADD comodin BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX idx_cliente_comodin ON cliente (comodin) WHERE comodin');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cliente_comodin');
        $this->addSql('ALTER TABLE cliente DROP comodin');
    }
}
