<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Navegación por capacidad (ADR-049):
 *
 * - `vue_route.capacidades` y `vue_route.recurso`: lo que exige cada pantalla,
 *   tal como lo declara el router del frontend (llega con la sincronización
 *   de rutas, que la hace el primero que entra con `config.editar`).
 * - La audiencia de menús, cajas y enlaces es solo por roles y «sin roles» es
 *   «a todos». Para que nadie vea ni deje de ver algo por el cambio:
 *   - un menú sin roles (antes no lo veía nadie) sale de las áreas;
 *   - lo que llevaba `ROLE_USER` (el rol base: todos) queda sin roles;
 *   - el rol de sistema sale de las listas que tienen otros roles (lo ve
 *     igual); donde es el único, se queda: «solo el de sistema».
 * - Fuera los usuarios sueltos de cajas y enlaces (excepciones por usuario,
 *   ADR-039). Si hubiera alguno, se detiene: hay que decidir a mano qué rol
 *   le corresponde.
 * - Fuera el rol base `ROLE_USER` (solo servía para la audiencia).
 */
final class Version20261103120000 extends AbstractMigration
{
    private const BASE = "(SELECT id FROM role WHERE nombre = 'ROLE_USER')";
    private const SISTEMA = '(SELECT id FROM role WHERE sistema)';

    public function getDescription(): string
    {
        return 'Navegación por capacidad: lo que exige cada ruta, audiencia solo por roles (sin roles = a todos) y sin rol base';
    }

    public function up(Schema $schema): void
    {
        foreach (['seccion_administracion_usuario', 'enlace_administracion_usuario'] as $tabla) {
            $usuarios = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM {$tabla}");
            $this->abortIf($usuarios > 0, "{$tabla} tiene {$usuarios} filas: la audiencia ahora es solo por roles. Páselas a un rol (o quítelas) y vuelva a migrar.");
        }

        $this->addSql("ALTER TABLE vue_route ADD capacidades JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE vue_route ADD recurso JSON DEFAULT NULL');

        // Antes, sin roles no lo veía nadie: que siga sin verse.
        $this->addSql('DELETE FROM menu_placement p WHERE NOT EXISTS (SELECT 1 FROM menu_role r WHERE r.menu_id = p.menu_id)');

        // El rol base lo tenían todos: con él, a todos (sin roles).
        $this->addSql('DELETE FROM menu_role WHERE menu_id IN (SELECT menu_id FROM menu_role WHERE role_id = ' . self::BASE . ')');
        $this->addSql('DELETE FROM seccion_administracion_role WHERE seccion_administracion_id IN (SELECT seccion_administracion_id FROM seccion_administracion_role WHERE role_id = ' . self::BASE . ')');
        $this->addSql('DELETE FROM enlace_administracion_role WHERE enlace_administracion_id IN (SELECT enlace_administracion_id FROM enlace_administracion_role WHERE role_id = ' . self::BASE . ')');

        // El de sistema ve todo: nombrarlo junto a otros no cambia nada.
        foreach (['menu_role' => 'menu_id', 'seccion_administracion_role' => 'seccion_administracion_id', 'enlace_administracion_role' => 'enlace_administracion_id'] as $tabla => $columna) {
            $this->addSql("DELETE FROM {$tabla} t WHERE t.role_id IN " . self::SISTEMA . " AND EXISTS (SELECT 1 FROM {$tabla} o WHERE o.{$columna} = t.{$columna} AND o.role_id NOT IN " . self::SISTEMA . ')');
        }

        $this->addSql('DROP TABLE seccion_administracion_usuario');
        $this->addSql('DROP TABLE enlace_administracion_usuario');

        // Sin asignaciones (nunca se asignó) ni usos que queden: las uniones caen en cascada.
        $this->addSql("DELETE FROM role WHERE nombre = 'ROLE_USER'");
    }

    /** Solo el esquema: la audiencia que tenía cada cosa no vuelve. */
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vue_route DROP capacidades');
        $this->addSql('ALTER TABLE vue_route DROP recurso');
        $this->addSql("INSERT INTO role (nombre, descripcion, capacidades, limites, sistema) SELECT 'ROLE_USER', 'Todos los usuarios: menús y cajas de administración comunes', '[]', '{}', false WHERE NOT EXISTS (SELECT 1 FROM role WHERE nombre = 'ROLE_USER')");
        $this->addSql('CREATE TABLE seccion_administracion_usuario (seccion_administracion_id INT NOT NULL, usuario_id INT NOT NULL, PRIMARY KEY (seccion_administracion_id, usuario_id))');
        $this->addSql('CREATE INDEX IDX_C0B2DBBBF0685708 ON seccion_administracion_usuario (seccion_administracion_id)');
        $this->addSql('CREATE INDEX IDX_C0B2DBBBDB38439E ON seccion_administracion_usuario (usuario_id)');
        $this->addSql('ALTER TABLE seccion_administracion_usuario ADD CONSTRAINT FK_C0B2DBBBF0685708 FOREIGN KEY (seccion_administracion_id) REFERENCES seccion_administracion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seccion_administracion_usuario ADD CONSTRAINT FK_C0B2DBBBDB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE enlace_administracion_usuario (enlace_administracion_id INT NOT NULL, usuario_id INT NOT NULL, PRIMARY KEY (enlace_administracion_id, usuario_id))');
        $this->addSql('CREATE INDEX IDX_14A0A794C7A0682F ON enlace_administracion_usuario (enlace_administracion_id)');
        $this->addSql('CREATE INDEX IDX_14A0A794DB38439E ON enlace_administracion_usuario (usuario_id)');
        $this->addSql('ALTER TABLE enlace_administracion_usuario ADD CONSTRAINT FK_14A0A794C7A0682F FOREIGN KEY (enlace_administracion_id) REFERENCES enlace_administracion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enlace_administracion_usuario ADD CONSTRAINT FK_14A0A794DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE');
    }
}
