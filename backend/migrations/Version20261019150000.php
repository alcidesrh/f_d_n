<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grupos de menú: un `MenuItem` sin `route` agrupa a sus hijos sin navegar
 * (p. ej. «Reporte» dentro de «Boleto»). Quita el NOT NULL de `route_id`;
 * los ítems existentes conservan su ruta.
 */
final class Version20261019150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Menú: ítems sin ruta (grupos que solo agrupan hijos)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item ALTER COLUMN route_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM taxonomy_node WHERE subject_class = \'MenuItem\' AND subject_id IN (SELECT id FROM menu_item WHERE route_id IS NULL)');
        $this->addSql('DELETE FROM menu_item WHERE route_id IS NULL');
        $this->addSql('ALTER TABLE menu_item ALTER COLUMN route_id SET NOT NULL');
    }
}
