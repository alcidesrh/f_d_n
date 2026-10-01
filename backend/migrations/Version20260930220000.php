<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Agrega `alias`, `nombre_comercial` y `denominacion_social` a empresa, con los valores del legado. */
final class Version20260930220000 extends AbstractMigration
{
    private const DATOS = [
        1 => ['PIONERA', 'Fuente del Norte la Pionera', 'Transportes Fuente del Norte la Pionera, S.A'],
        2 => ['MAYA DE ORO', 'Maya De Oro', 'AUTOBUSES MAYA DE ORO, SOCIEDAS ANONIMA'],
        3 => ['EDWIN', 'Transportes Fuente del Norte', 'Edwin Alfredo Mendoza Matta'],
        4 => ['MITOCHA', 'Autobuses Fuente del Norte ADO Y MITOCHA', 'Autobuses Fuente del Norte ADO Y MITOCHA'],
        6 => ['STARBUS-TRPACIF', 'START BUS, S.A - TRANSPACIFIC', 'START BUS, S.A'],
        7 => ['ROSITA', 'Transportes Rosita', 'Transportes Rosita, S.A.'],
        8 => ['CORP PIONERA', 'Corporación la Pionera', 'Corporación la Pionera, S.A.'],
    ];

    public function getDescription(): string
    {
        return 'Empresa: alias, nombre comercial y denominación social (del legado)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa ADD alias VARCHAR(15) DEFAULT NULL');
        $this->addSql('ALTER TABLE empresa ADD nombre_comercial VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE empresa ADD denominacion_social VARCHAR(255) DEFAULT NULL');

        foreach (self::DATOS as $id => [$alias, $comercial, $social]) {
            $this->addSql(
                'UPDATE empresa SET alias = ?, nombre_comercial = ?, denominacion_social = ? WHERE id = ?',
                [$alias, $comercial, $social, $id],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa DROP alias');
        $this->addSql('ALTER TABLE empresa DROP nombre_comercial');
        $this->addSql('ALTER TABLE empresa DROP denominacion_social');
    }
}
