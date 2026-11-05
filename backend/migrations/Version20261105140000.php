<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Usuario: fecha del último inicio de sesión (`last_login`), la fija `POST /api/login`.
 */
final class Version20261105140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Usuario: columna last_login (último inicio de sesión)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD last_login TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP last_login');
    }
}
