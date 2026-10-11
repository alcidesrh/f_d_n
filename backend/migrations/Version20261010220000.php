<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ocupación por orden de paradas (ADR-030, fase 4): el tramo `[orden_sube,
 * orden_baja)` es obligatorio en boletos y reservas, y dos boletos vivos del
 * mismo asiento y salida no pueden solaparse (restricción de exclusión con
 * `btree_gist`; Doctrine la ignora, ver `ExclusionConstraintsSchemaManager`).
 * Reemplaza al índice único parcial `(asiento, trayecto, salida)`, que no
 * impedía vender A→C y B→D en el mismo asiento.
 *
 * Requiere el tramo de todos los boletos y reservas (`app:ruta:rellenar`).
 */
final class Version20261010220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tramo por orden obligatorio y sin solapes en boleto_asiento (ADR-030, fase 4)';
    }

    public function up(Schema $schema): void
    {
        foreach (["boleto_asiento", "reserva_asiento"] as $tabla) {
            $sinTramo = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM {$tabla} WHERE orden_sube IS NULL OR orden_baja IS NULL");
            $this->abortIf($sinTramo > 0, "{$sinTramo} filas de {$tabla} sin tramo: ejecute antes `bin/console app:ruta:rellenar`.");
        }
        foreach (["boleto_asiento", "reserva_asiento"] as $tabla) {
            $this->addSql("ALTER TABLE {$tabla} ALTER orden_sube SET NOT NULL");
            $this->addSql("ALTER TABLE {$tabla} ALTER orden_baja SET NOT NULL");
            $this->addSql("ALTER TABLE {$tabla} ADD CONSTRAINT ck_{$tabla}_tramo CHECK (orden_sube >= 0 AND orden_sube < orden_baja)");
        }
        $this->addSql('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $this->addSql("ALTER TABLE boleto_asiento ADD CONSTRAINT ex_boleto_asiento_tramo EXCLUDE USING gist (salida_id WITH =, asiento_id WITH =, int4range(orden_sube, orden_baja) WITH &&) WHERE (estado NOT IN ('anulado', 'reasignado'))");
        $this->addSql('DROP INDEX uq_boleto_asiento_asiento_trayecto_salida');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("CREATE UNIQUE INDEX uq_boleto_asiento_asiento_trayecto_salida ON boleto_asiento (asiento_id, trayecto_id, salida_id) WHERE estado NOT IN ('anulado', 'reasignado')");
        $this->addSql('ALTER TABLE boleto_asiento DROP CONSTRAINT ex_boleto_asiento_tramo');
        foreach (["boleto_asiento", "reserva_asiento"] as $tabla) {
            $this->addSql("ALTER TABLE {$tabla} DROP CONSTRAINT ck_{$tabla}_tramo");
            $this->addSql("ALTER TABLE {$tabla} ALTER orden_sube DROP NOT NULL");
            $this->addSql("ALTER TABLE {$tabla} ALTER orden_baja DROP NOT NULL");
        }
    }
}
