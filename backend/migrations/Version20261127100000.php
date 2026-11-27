<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La apariencia de cada usuario ("Mi cuenta → Apariencia") en su cuenta, para
 * que lo elegido lo siga en otro equipo o en la app móvil. Vacía, el usuario
 * ve la de su navegador o la de fábrica.
 */
final class Version20261127100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Apariencia del usuario en su cuenta';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD apariencia JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP apariencia');
    }
}
