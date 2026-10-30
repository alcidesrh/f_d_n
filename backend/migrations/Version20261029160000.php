<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Avisos del sistema" pasa a llamarse "Bitácora del sistema": el nombre de
 * cada canal de sistema ya creado se actualiza (los nuevos lo toman de
 * `ChatCanal::sistema`).
 */
final class Version20261029160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chat: canal de sistema renombrado a "Bitácora del sistema"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE chat_canal SET nombre = 'Bitácora del sistema' WHERE tipo = 'sistema'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE chat_canal SET nombre = 'Avisos del sistema' WHERE tipo = 'sistema'");
    }
}
