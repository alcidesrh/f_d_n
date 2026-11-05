<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enlace «Programador de tareas» en la administración, detrás de «Publicar
 * configuración» (o de «Configuración de entidades»). Va en una migración
 * propia: la de ADR-044 ya corrió en las bases existentes.
 */
final class Version20261105150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Administración: enlace al programador de tareas';
    }

    public function up(Schema $schema): void
    {
        $previo = "(SELECT id FROM enlace_administracion WHERE ruta IN ('metadata-publicacion', 'entity-config') ORDER BY ruta = 'metadata-publicacion' DESC, id LIMIT 1)";
        $this->addSql("UPDATE enlace_administracion SET posicion = posicion + 1 WHERE seccion_id = (SELECT seccion_id FROM enlace_administracion WHERE id = {$previo}) AND posicion > (SELECT posicion FROM enlace_administracion WHERE id = {$previo})");
        $this->addSql(
            "INSERT INTO enlace_administracion (seccion_id, etiqueta, icono, claves, ruta, parametros, posicion, activo)
             SELECT seccion_id, ?, ?, ?, ?, ?, posicion + 1, true FROM enlace_administracion WHERE id = {$previo}",
            ['Programador de tareas', 'schedule', json_encode(['Tareas programadas', 'Reportes diarios', 'Worker', 'Cron'], JSON_UNESCAPED_UNICODE), 'programador-tareas', '{}'],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE enlace_administracion SET posicion = posicion - 1 WHERE seccion_id = (SELECT seccion_id FROM enlace_administracion WHERE ruta = 'programador-tareas') AND posicion > (SELECT posicion FROM enlace_administracion WHERE ruta = 'programador-tareas')");
        $this->addSql("DELETE FROM enlace_administracion WHERE ruta = 'programador-tareas'");
    }
}
