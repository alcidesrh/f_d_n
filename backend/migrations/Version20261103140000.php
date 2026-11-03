<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Chat: grupos fijados. El fijado es de cada miembro (`chat_miembro.fijado`),
 * así que sale solo con la membresía: al salir del grupo, el pin se pierde.
 */
final class Version20261103140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chat: grupos fijados por miembro (chat_miembro.fijado)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_miembro ADD fijado BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_miembro DROP fijado');
    }
}
