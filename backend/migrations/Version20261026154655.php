<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026154655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Empresa: activa (solo las activas aparecen en filtros y selectores)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa ADD activa BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa DROP activa');
    }
}
