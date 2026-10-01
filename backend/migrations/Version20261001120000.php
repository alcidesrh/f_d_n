<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Asignación de tarifa (ADR-021): `boleto_tarifa.trayecto_id` pasa a ser
 * obligatorio y `boleto_tarifa.clase` opcional (null = cualquier clase).
 *
 * Las tarifas sin trayecto ya no aplican a ninguna venta y nada las referencia
 * (las líneas de venta guardan el precio, no la tarifa), así que se borran.
 */
final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'BoletoTarifa: trayecto obligatorio y clase opcional (comodín)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM boleto_tarifa WHERE trayecto_id IS NULL');
        $this->addSql('ALTER TABLE boleto_tarifa ALTER COLUMN trayecto_id SET NOT NULL');
        $this->addSql('ALTER TABLE boleto_tarifa ALTER COLUMN clase DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM boleto_tarifa WHERE clase IS NULL');
        $this->addSql('ALTER TABLE boleto_tarifa ALTER COLUMN clase SET NOT NULL');
        $this->addSql('ALTER TABLE boleto_tarifa ALTER COLUMN trayecto_id DROP NOT NULL');
    }
}
