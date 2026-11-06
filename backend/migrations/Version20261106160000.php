<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suplantación de usuarios (`App\Acceso\Suplantacion`):
 *
 * - `api_token.suplantado_por_id` y `motivo`: el token de suplantación lleva
 *   quién lo emitió y por qué.
 * - `configuracion_suplantacion`: una fila (`App\Entity\ConfiguracionSuplantacion`).
 * - `actividad.suplantado_por_id`: quién hizo de verdad cada petición hecha
 *   como otro usuario (tabla particionada: la columna pasa a cada partición).
 */
final class Version20261106160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suplantación de usuarios: token marcado, configuración y suplantado_por_id en la bitácora diaria';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_token ADD suplantado_por_id INT DEFAULT NULL, ADD motivo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_7BA2F5EBCFD5E96 FOREIGN KEY (suplantado_por_id) REFERENCES usuario (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_7BA2F5EBCFD5E96 ON api_token (suplantado_por_id)');

        $this->addSql('CREATE TABLE configuracion_suplantacion (id INT NOT NULL, activa BOOLEAN DEFAULT true NOT NULL, duracion_minutos INT DEFAULT 60 NOT NULL, chat VARCHAR(10) DEFAULT \'lectura\' NOT NULL, avisar_usuario BOOLEAN DEFAULT true NOT NULL, exigir_motivo BOOLEAN DEFAULT true NOT NULL, actualizada_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, actualizada_por_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_57CF026AE39C682E ON configuracion_suplantacion (actualizada_por_id)');
        $this->addSql('ALTER TABLE configuracion_suplantacion ADD CONSTRAINT FK_57CF026AE39C682E FOREIGN KEY (actualizada_por_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('INSERT INTO configuracion_suplantacion (id) VALUES (1)');

        $this->addSql('ALTER TABLE actividad ADD suplantado_por_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE actividad DROP suplantado_por_id');
        $this->addSql('DROP TABLE configuracion_suplantacion');
        $this->addSql('ALTER TABLE api_token DROP CONSTRAINT FK_7BA2F5EBCFD5E96');
        $this->addSql('DROP INDEX IDX_7BA2F5EBCFD5E96');
        $this->addSql('ALTER TABLE api_token DROP suplantado_por_id, DROP motivo');
    }
}
