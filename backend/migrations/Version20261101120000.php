<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `empresa.activa` se agregó sin valor (NULL): como los filtros piden
 * `activa = true`, ninguna empresa sin tocar aparecía. Las que no se
 * desactivaron a mano pasan a activas, y las nuevas nacen activas (también
 * las que inserta la migración del legado por DBAL).
 */
final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Empresa: activa por defecto (las NULL pasan a true)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa ALTER activa SET DEFAULT true');
        $this->addSql('UPDATE empresa SET activa = true WHERE activa IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa ALTER activa SET DEFAULT NULL');
    }
}
