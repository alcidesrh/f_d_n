<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Usuario.foto: ruta de la foto de perfil (Mi cuenta)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD foto VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP foto');
    }
}
