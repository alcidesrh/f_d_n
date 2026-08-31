<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260828125710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colapsa trayectos duplicados (origen_id, destino_id) y crea índice único.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE subtrayecto s SET trayecto_id = (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id) FROM trayecto t WHERE t.id = s.trayecto_id AND s.trayecto_id <> (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id)');
        $this->addSql('UPDATE subtrayecto s SET below_to_id = (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id) FROM trayecto t WHERE t.id = s.below_to_id AND s.below_to_id <> (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id)');
        $this->addSql('UPDATE boleto_tarifa bt SET trayecto_id = (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id) FROM trayecto t WHERE t.id = bt.trayecto_id AND bt.trayecto_id IS NOT NULL AND bt.trayecto_id <> (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id)');
        $this->addSql('UPDATE boleto_asiento ba SET trayecto_id = (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id) FROM trayecto t WHERE t.id = ba.trayecto_id AND ba.trayecto_id IS NOT NULL AND ba.trayecto_id <> (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id)');
        $this->addSql('DELETE FROM trayecto t WHERE t.id <> (SELECT MIN(t2.id) FROM trayecto t2 WHERE t2.origen_id = t.origen_id AND t2.destino_id = t.destino_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_trayecto_origen_destino ON trayecto (origen_id, destino_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uq_trayecto_origen_destino');
    }
}
