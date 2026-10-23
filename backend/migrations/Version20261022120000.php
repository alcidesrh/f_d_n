<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enlaces en el menú (ADR-018): `menu_item.path`, un destino escrito a mano
 * para ítems que no nacen de una `vue_route`. Excluyente con `route`; los
 * existentes quedan igual (ruta o grupo).
 */
final class Version20261022120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ítems de menú: path (enlace escrito a mano, excluyente con route)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item ADD path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item DROP path');
    }
}
