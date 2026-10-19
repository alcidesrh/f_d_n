<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `enclave.alias`: la sigla de la estación (GUA, SEP) que el legado imprime
 * en grande en las guías de encomienda (ADR-035). La copia del legado
 * `app:encomienda:legado`.
 */
final class Version20261019040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alias de enclave para las guías de encomienda';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enclave ADD alias VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enclave DROP alias');
    }
}
