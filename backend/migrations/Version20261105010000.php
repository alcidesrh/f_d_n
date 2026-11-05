<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bitácora diaria de los usuarios:
 *
 * - `actividad`: cada petición, sesión y acción de pantalla de un usuario.
 *   Particionada por mes (`actividad_AAAA_MM`, más `actividad_otros` por si
 *   falta una partición): borrar un mes viejo es un `DROP`. Solo DBAL, fuera
 *   del ORM y del diff de migraciones (`schema_filter`). La tarea
 *   `bitacora_diaria` crea las particiones que vienen y borra las vencidas.
 * - `actividad_resumen_diario`: una fila por usuario y día (sin límite por defecto).
 * - `configuracion_bitacora`: una fila (`App\Entity\ConfiguracionBitacora`).
 * - `bitacora.request_id`, que une un cambio con la petición que lo hizo, y
 *   el índice por usuario y fecha (la bitácora diaria y `ConteoDiario`), que
 *   cubre al de `usuario_id` solo.
 */
final class Version20261105010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bitácora diaria: actividad (particionada), resumen diario, configuración y bitacora.request_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE configuracion_bitacora (id INT NOT NULL, activa BOOLEAN DEFAULT true NOT NULL, nivel VARCHAR(10) DEFAULT \'medio\' NOT NULL, familias_apagadas JSON DEFAULT \'[]\' NOT NULL, alcance VARCHAR(15) DEFAULT \'todos\' NOT NULL, seleccion JSON DEFAULT \'[]\' NOT NULL, retencion_meses INT DEFAULT 2 NOT NULL, exportar_antes_de_borrar BOOLEAN DEFAULT true NOT NULL, retencion_resumen_meses INT DEFAULT 0 NOT NULL, hora_resumen VARCHAR(5) DEFAULT \'00:15\' NOT NULL, intervalo_pantalla_segundos INT DEFAULT 30 NOT NULL, max_detalle_kb INT DEFAULT 4 NOT NULL, campos_extra JSON DEFAULT \'[]\' NOT NULL, cambios_entidades BOOLEAN DEFAULT true NOT NULL, actualizada_en TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, actualizada_por_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3816E960E39C682E ON configuracion_bitacora (actualizada_por_id)');
        $this->addSql('ALTER TABLE configuracion_bitacora ADD CONSTRAINT FK_3816E960E39C682E FOREIGN KEY (actualizada_por_id) REFERENCES usuario (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('INSERT INTO configuracion_bitacora (id) VALUES (1)');

        $this->addSql('ALTER TABLE bitacora ADD request_id UUID DEFAULT NULL');
        $this->addSql('DROP INDEX IF EXISTS IDX_9087FEF9DB38439E');
        $this->addSql('CREATE INDEX idx_bitacora_usuario_fecha ON bitacora (usuario_id, fecha)');

        $this->addSql(<<<'SQL'
            CREATE TABLE actividad (
                id BIGSERIAL NOT NULL,
                fecha TIMESTAMP(3) WITH TIME ZONE NOT NULL,
                usuario_id INT DEFAULT NULL,
                empresa_id INT DEFAULT NULL,
                estacion_id INT DEFAULT NULL,
                tipo VARCHAR(10) NOT NULL,
                accion VARCHAR(60) DEFAULT NULL,
                metodo VARCHAR(7) DEFAULT NULL,
                ruta VARCHAR(120) DEFAULT NULL,
                estado SMALLINT DEFAULT NULL,
                duracion_ms INT DEFAULT NULL,
                ip INET DEFAULT NULL,
                agente VARCHAR(255) DEFAULT NULL,
                request_id UUID DEFAULT NULL,
                entidad VARCHAR(20) DEFAULT NULL,
                registro_id BIGINT DEFAULT NULL,
                detalle JSONB DEFAULT NULL,
                PRIMARY KEY (id, fecha)
            ) PARTITION BY RANGE (fecha)
            SQL);
        $this->addSql('CREATE INDEX idx_actividad_usuario_fecha ON actividad (usuario_id, fecha)');
        $this->addSql('CREATE INDEX idx_actividad_fecha ON actividad USING brin (fecha)');
        $this->addSql('CREATE TABLE actividad_otros PARTITION OF actividad DEFAULT');
        // Este mes y los dos siguientes; después las crea la tarea `bitacora_diaria`.
        $mes = new \DateTimeImmutable('first day of this month 00:00', new \DateTimeZone('America/Guatemala'));
        for ($i = 0; $i < 3; ++$i) {
            $desde = $mes->modify("+{$i} months");
            $hasta = $desde->modify('+1 month');
            $this->addSql(sprintf(
                "CREATE TABLE actividad_%s PARTITION OF actividad FOR VALUES FROM ('%s') TO ('%s')",
                $desde->format('Y_m'),
                $desde->format('Y-m-d H:i:sP'),
                $hasta->format('Y-m-d H:i:sP'),
            ));
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE actividad_resumen_diario (
                dia DATE NOT NULL,
                usuario_id INT NOT NULL,
                empresa_id INT DEFAULT NULL,
                estacion_id INT DEFAULT NULL,
                primera TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                ultima TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                minutos_activos INT DEFAULT 0 NOT NULL,
                sesiones INT DEFAULT 0 NOT NULL,
                peticiones INT DEFAULT 0 NOT NULL,
                escrituras INT DEFAULT 0 NOT NULL,
                errores INT DEFAULT 0 NOT NULL,
                sondeos INT DEFAULT 0 NOT NULL,
                pantallas INT DEFAULT 0 NOT NULL,
                cambios INT DEFAULT 0 NOT NULL,
                operaciones JSONB DEFAULT '{}' NOT NULL,
                PRIMARY KEY (dia, usuario_id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_actividad_resumen_usuario ON actividad_resumen_diario (usuario_id, dia)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE actividad_resumen_diario');
        $this->addSql('DROP TABLE actividad');
        $this->addSql('DROP INDEX idx_bitacora_usuario_fecha');
        $this->addSql('CREATE INDEX IDX_9087FEF9DB38439E ON bitacora (usuario_id)');
        $this->addSql('ALTER TABLE bitacora DROP request_id');
        $this->addSql('DROP TABLE configuracion_bitacora');
    }
}
