<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Índices para lo que se leía recorriendo la tabla entera (ADR-056):
 *
 * - `boleto_asiento.legacy_id` y `factura.uuid`: por ellos busca la
 *   sincronización con el legado en cada ciclo (ADR-042).
 * - `boleto_venta.created_at`: el listado de boletos y los reportes filtran
 *   por fecha de venta sin estado, y el índice que había empieza por `estado`.
 * - Las ventas que esperan factura: unas pocas entre todas, las leen la tarea
 *   que las certifica y el tablero (índice parcial).
 * - `bitacora.fecha`: el resumen diario y las incidencias del día.
 * - Clientes: `numero_documento`, el NIT por prefijo y el nombre por
 *   cualquier parte (trigramas), que es la búsqueda de la pantalla de venta.
 *
 * Los `nat_*` usan lo que el mapeo de Doctrine no sabe expresar (clase de
 * operadores, GIN sobre una expresión): solo existen aquí y el diff del
 * esquema los ignora (`ExclusionConstraintsSchemaManager`). La expresión de
 * `nat_cliente_busqueda` es, letra por letra, la de `Clientes::buscar`.
 */
final class Version20261111110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Índices de sincronización, ventas por fecha, facturas pendientes, bitácora y búsqueda de clientes (ADR-056)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_boleto_asiento_legacy ON boleto_asiento (legacy_id) WHERE (legacy_id IS NOT NULL)');
        $this->addSql('CREATE INDEX idx_factura_uuid ON factura (uuid)');
        $this->addSql('CREATE INDEX idx_boleto_venta_creada ON boleto_venta (created_at)');
        $this->addSql("CREATE INDEX idx_boleto_venta_fel_pendiente ON boleto_venta (created_at) WHERE ((estado_facturacion)::text = 'pendiente'::text)");
        $this->addSql('CREATE INDEX idx_bitacora_fecha ON bitacora (fecha)');
        $this->addSql('CREATE INDEX idx_cliente_documento ON cliente (numero_documento)');

        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE INDEX nat_cliente_nit_prefijo ON cliente (nit varchar_pattern_ops)');
        $this->addSql(
            "CREATE INDEX nat_cliente_busqueda ON cliente
              USING gin (LOWER(nombre || ' ' || COALESCE(apellido, '') || ' ' || COALESCE(nit, '')) gin_trgm_ops)",
        );
    }

    public function down(Schema $schema): void
    {
        foreach ([
            'nat_cliente_busqueda', 'nat_cliente_nit_prefijo', 'idx_cliente_documento', 'idx_bitacora_fecha',
            'idx_boleto_venta_fel_pendiente', 'idx_boleto_venta_creada', 'idx_factura_uuid', 'idx_boleto_asiento_legacy',
        ] as $indice) {
            $this->addSql("DROP INDEX $indice");
        }
    }
}
