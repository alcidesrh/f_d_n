<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Foto de los grupos del chat (`App\Chat\FotoGrupo`): ruta relativa, solo en los de grupo. */
final class Version20261028120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Foto de los grupos del chat';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_canal ADD foto VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_canal DROP foto');
    }
}
