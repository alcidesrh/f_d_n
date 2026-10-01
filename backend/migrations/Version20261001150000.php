<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `boleto_venta.voucher`: boletos del legado emitidos con voucher (no se
 * cobraron). La migración del legado lo llena; en bases ya migradas,
 * `app:venta:vouchers-legado`. Idempotente.
 */
final class Version20261001150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'BoletoVenta.voucher (boletos del legado emitidos con voucher)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boleto_venta ADD COLUMN IF NOT EXISTS voucher BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boleto_venta DROP COLUMN IF EXISTS voucher');
    }
}
