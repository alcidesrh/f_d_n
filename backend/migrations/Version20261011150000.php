<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retiro (ADR-030, fase 8):
 *
 * - `subtrayecto`: el orden de las paradas está en `parada`.
 * - `trayecto.distancia_km`, `duracion_estimada_minutos` y `legacy_id`: la
 *   distancia y los minutos son de la ruta y sus paradas; el código del
 *   legado es de la ruta.
 * - Trayectos huérfanos: sin boletos, reservas ni tarifas y que no son par
 *   de paradas de ninguna ruta (casi todos de las rutas inversas que inventaba
 *   el migrador).
 * - La configuración dinámica de la entidad `Subtrayecto`.
 */
final class Version20261011150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fuera subtrayecto, columnas viejas de trayecto y trayectos huérfanos (ADR-030, fase 8)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE subtrayecto');
        $this->addSql('ALTER TABLE trayecto DROP distancia_km, DROP duracion_estimada_minutos, DROP legacy_id');
        $this->addSql(
            "DELETE FROM trayecto t
              WHERE NOT EXISTS (SELECT 1 FROM boleto_asiento b WHERE b.trayecto_id = t.id)
                AND NOT EXISTS (SELECT 1 FROM reserva_asiento r WHERE r.trayecto_id = t.id)
                AND NOT EXISTS (SELECT 1 FROM boleto_tarifa bt WHERE bt.trayecto_id = t.id)
                AND NOT EXISTS (SELECT 1 FROM parada o JOIN parada d ON d.ruta_id = o.ruta_id AND d.orden > o.orden
                                 WHERE o.enclave_id = t.origen_id AND d.enclave_id = t.destino_id)",
        );
        $this->addSql("DELETE FROM collection_field_config WHERE entity_config_id IN (SELECT id FROM entity_configuration WHERE entity_class = 'Subtrayecto')");
        $this->addSql("DELETE FROM form_field_config WHERE entity_config_id IN (SELECT id FROM entity_configuration WHERE entity_class = 'Subtrayecto')");
        $this->addSql("DELETE FROM entity_configuration WHERE entity_class = 'Subtrayecto'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Los subtrayectos y los trayectos huérfanos no se reconstruyen; vuelva a migrar desde el legado.');
    }
}
