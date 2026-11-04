<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seguimiento (ADR-050), temporal: lecturas GPS de los teléfonos de prueba,
 * que se siguen con un nombre acordado en vez del código de un bus.
 */
final class Version20261104010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seguimiento: lecturas GPS de los teléfonos de prueba (lectura_gps_prueba)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lectura_gps_prueba (dispositivo VARCHAR(64) NOT NULL, instante TIMESTAMP(0) WITH TIME ZONE NOT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, rumbo SMALLINT NOT NULL, velocidad_kmh SMALLINT NOT NULL, PRIMARY KEY (dispositivo, instante))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lectura_gps_prueba');
    }
}
