<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `boleto_venta.facturas_anuladas`: cuántas facturas de la venta se anularon
 * en el certificador. La factura nueva de una reemisión (modificar la compra,
 * cambiar los datos de facturación, anular parte de una factura conjunta)
 * lleva otro código interno (`FDN-VENTA-{id}-R{n}`): con el mismo, Forcon la
 * rechazaba con ESW-025 y la venta quedaba sin factura.
 *
 * Se llena con las facturas anuladas cuyo XML certificado lleva el código
 * interno de la venta (las del certificador simulado no tienen XML).
 */
final class Version20261114100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facturas anuladas por venta: la factura de una reemisión lleva otro código interno';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boleto_venta ADD facturas_anuladas INT DEFAULT 0 NOT NULL');
        $this->addSql(<<<'SQL'
            UPDATE boleto_venta v SET facturas_anuladas = a.n
            FROM (
                SELECT substring(f.xml from 'FDN-VENTA-([0-9]+)')::int AS venta, count(*) AS n
                FROM factura f
                WHERE f.anulada_en IS NOT NULL AND f.xml LIKE '%FDN-VENTA-%'
                GROUP BY 1
            ) a
            WHERE v.id = a.venta
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boleto_venta DROP facturas_anuladas');
    }
}
