<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Acciones sobre las compras de la página web (dashboard `/venta-en-linea`):
 *
 * - Estado `pospuesto` del boleto: la compra se pospone, el asiento queda
 *   libre (sale de `ex_boleto_asiento_tramo` como anulado, reasignado y
 *   cancelado) y se reanuda reasignándolo.
 * - `boleto_venta.correo_enviado_en`: cuándo se envió el boleto por correo.
 *   Las ventas anteriores quedan sin fecha (no se sabe).
 */
final class Version20261015180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Boleto pospuesto y fecha de envío del boleto por correo';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $this->addSql('ALTER TABLE boleto_asiento DROP CONSTRAINT IF EXISTS ex_boleto_asiento_tramo');
        $this->addSql("ALTER TABLE boleto_asiento ADD CONSTRAINT ex_boleto_asiento_tramo EXCLUDE USING gist (salida_id WITH =, asiento_id WITH =, int4range(orden_sube, orden_baja) WITH &&) WHERE (estado NOT IN ('anulado', 'reasignado', 'cancelado', 'pospuesto'))");
        $this->addSql('ALTER TABLE boleto_venta ADD correo_enviado_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boleto_venta DROP correo_enviado_en');
        $this->addSql('ALTER TABLE boleto_asiento DROP CONSTRAINT IF EXISTS ex_boleto_asiento_tramo');
        $this->addSql("ALTER TABLE boleto_asiento ADD CONSTRAINT ex_boleto_asiento_tramo EXCLUDE USING gist (salida_id WITH =, asiento_id WITH =, int4range(orden_sube, orden_baja) WITH &&) WHERE (estado NOT IN ('anulado', 'reasignado', 'cancelado'))");
    }
}
