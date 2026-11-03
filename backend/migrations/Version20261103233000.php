<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seguimiento (ADR-050): historial de lecturas GPS por bus, para reproducir la
 * trayectoria de una salida. La clave (bus, instante) evita guardar dos veces
 * lo que Traccar reenvía y sirve para leer y purgar por bus.
 */
final class Version20261103233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seguimiento: historial de lecturas GPS (lectura_gps)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lectura_gps (instante TIMESTAMP(0) WITH TIME ZONE NOT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, rumbo SMALLINT NOT NULL, velocidad_kmh SMALLINT NOT NULL, bus_id INT NOT NULL, PRIMARY KEY (bus_id, instante))');
        $this->addSql('CREATE INDEX IDX_2F5ECEB62546731D ON lectura_gps (bus_id)');
        $this->addSql('ALTER TABLE lectura_gps ADD CONSTRAINT FK_2F5ECEB62546731D FOREIGN KEY (bus_id) REFERENCES bus (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lectura_gps');
    }
}
