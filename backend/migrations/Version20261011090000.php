<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La salida y el esquema de salidas eligen una ruta (ADR-030, fase 5):
 * `ruta_id` obligatorio y fuera `trayecto_id`. El trayecto de las puntas se
 * deduce del horario de la salida. Requiere que todas tengan ruta (una base
 * migrada antes de ADR-030 se vuelve a migrar desde el legado).
 */
final class Version20261011090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Salida y esquema de salidas con ruta obligatoria; sin trayecto (ADR-030, fase 5)';
    }

    public function up(Schema $schema): void
    {
        foreach (["salida", "esquema_salida"] as $tabla) {
            $sinRuta = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM {$tabla} WHERE ruta_id IS NULL");
            $this->abortIf($sinRuta > 0, "{$sinRuta} filas de {$tabla} sin ruta.");
        }
        $this->addSql('ALTER TABLE salida DROP CONSTRAINT fk_95f4c7488e3e79aa');
        $this->addSql('DROP INDEX idx_95f4c7488e3e79aa');
        $this->addSql('DROP INDEX idx_salida_fecha_empresa_trayecto');
        $this->addSql('ALTER TABLE salida DROP trayecto_id');
        $this->addSql('ALTER TABLE salida ALTER ruta_id SET NOT NULL');
        $this->addSql('CREATE INDEX idx_salida_fecha_empresa_ruta ON salida (fecha, empresa_id, ruta_id)');
        $this->addSql('ALTER TABLE esquema_salida DROP CONSTRAINT fk_99694db48e3e79aa');
        $this->addSql('DROP INDEX idx_99694db48e3e79aa');
        $this->addSql('ALTER TABLE esquema_salida DROP trayecto_id');
        $this->addSql('ALTER TABLE esquema_salida ALTER ruta_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('El trayecto de cada salida no se conserva; restaure un respaldo.');
    }
}
