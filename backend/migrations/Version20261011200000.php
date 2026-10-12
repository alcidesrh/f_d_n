<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Finalizar salidas (ADR-032): acción `salida.finalizar` en el permiso "Abordaje de Salidas". */
final class Version20261011200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Acción salida.finalizar (ADR-032)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO action (codigo, nombre) SELECT 'salida.finalizar', 'Finalizar salidas' WHERE NOT EXISTS (SELECT 1 FROM action WHERE codigo = 'salida.finalizar')");
        $this->addSql("INSERT INTO permiso_action (permiso_id, action_id)
                       SELECT p.id, a.id FROM permiso p, action a WHERE p.nombre = 'Abordaje de Salidas' AND a.codigo = 'salida.finalizar'
                       ON CONFLICT DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM permiso_action WHERE action_id = (SELECT id FROM action WHERE codigo = 'salida.finalizar')");
        $this->addSql("DELETE FROM action WHERE codigo = 'salida.finalizar'");
    }
}
