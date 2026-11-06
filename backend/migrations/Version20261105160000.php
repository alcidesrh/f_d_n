<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sincronización (ADR-042): un cliente tiene el mismo id en los dos sistemas y
 * ya no hay rango de ids a partir de 1.000.000.000.
 *
 * - Los clientes de aquí con par en `sync_vinculo` pasan al id del legado.
 * - Los del rango sin par quedan anotados en `sync_cliente_local`: toman su id
 *   del legado al encender.
 * - Las secuencias de `cliente` y `usuario` vuelven al mayor id + 1.
 *
 * Se aplica con la sincronización apagada.
 */
final class Version20261105160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sincronización: mismo id de cliente en los dos sistemas, sin rango propio';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sync_cliente_local (cliente_id INT NOT NULL, creado_en TIMESTAMP(0) WITH TIME ZONE DEFAULT NOW() NOT NULL, PRIMARY KEY (cliente_id))');
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE v RECORD;
            BEGIN
                FOR v IN SELECT id_nuevo::int AS viejo, id_legado::int AS nuevo FROM sync_vinculo
                          WHERE entidad = 'cliente' AND id_nuevo IN (SELECT id FROM cliente) LOOP
                    INSERT INTO cliente SELECT (jsonb_populate_record(NULL::cliente, to_jsonb(c) || jsonb_build_object('id', v.nuevo))).*
                      FROM cliente c WHERE c.id = v.viejo ON CONFLICT (id) DO NOTHING;
                    UPDATE boleto_asiento SET cliente_id = v.nuevo WHERE cliente_id = v.viejo;
                    UPDATE boleto_venta SET cliente_id = v.nuevo WHERE cliente_id = v.viejo;
                    UPDATE encomienda SET remitente_id = v.nuevo WHERE remitente_id = v.viejo;
                    UPDATE encomienda SET destinatario_id = v.nuevo WHERE destinatario_id = v.viejo;
                    UPDATE encomienda SET receptor_id = v.nuevo WHERE receptor_id = v.viejo;
                    UPDATE encomienda SET cliente_factura_id = v.nuevo WHERE cliente_factura_id = v.viejo;
                    DELETE FROM cliente WHERE id = v.viejo;
                END LOOP;
            END $$
            SQL);
        $this->addSql('DROP TABLE sync_vinculo');
        $this->addSql('INSERT INTO sync_cliente_local (cliente_id) SELECT id FROM cliente WHERE id >= 1000000000');
        $this->addSql("SELECT setval(pg_get_serial_sequence('cliente', 'id'), COALESCE((SELECT MAX(id) FROM cliente WHERE id < 1000000000), 0) + 1, false)");
        $this->addSql("SELECT setval(pg_get_serial_sequence('usuario', 'id'), COALESCE((SELECT MAX(id) FROM usuario), 0) + 1, false)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sync_vinculo (entidad VARCHAR(20) NOT NULL, id_nuevo BIGINT NOT NULL, id_legado BIGINT NOT NULL, creado_en TIMESTAMP(0) WITH TIME ZONE DEFAULT NOW() NOT NULL, PRIMARY KEY (entidad, id_nuevo))');
        $this->addSql('CREATE UNIQUE INDEX uq_sync_vinculo_legado ON sync_vinculo (entidad, id_legado)');
        $this->addSql('DROP TABLE sync_cliente_local');
    }
}
