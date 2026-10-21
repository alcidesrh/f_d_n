<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enclaves y estaciones activas o inactivas (`Enclave.activo`). Todas quedan
 * activas: el default llena las filas existentes.
 */
final class Version20261020120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enclave: campo activo (true por defecto) para ocultar estaciones en las listas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enclave ADD activo BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enclave DROP activo');
    }
}
