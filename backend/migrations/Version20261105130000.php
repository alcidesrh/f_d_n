<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Roles en el listado de usuarios: la columna «asignaciones» (que no tenía
 * dato: la API no la expone) pasa a `rolesVigentes`, los roles con una
 * asignación vigente. El formulario de usuario deja de mostrarla: los roles
 * se editan en `/configuracion/usuarios/:id/accesos`.
 *
 * Solo cambia la configuración de la entidad Usuario; una etiqueta que se
 * haya cambiado a mano se respeta.
 */
final class Version20261105130000 extends AbstractMigration
{
    private const USUARIO = "(SELECT id FROM entity_configuration WHERE entity_class = 'Usuario')";

    public function getDescription(): string
    {
        return 'Listado de usuarios: la columna asignaciones pasa a rolesVigentes; el formulario no la muestra';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE collection_field_config SET field = 'rolesVigentes', label = CASE WHEN label = 'asignaciones' THEN 'roles' ELSE label END "
            . "WHERE field = 'asignaciones' AND list_config_id IN (SELECT id FROM list_configuration WHERE entity_config_id = " . self::USUARIO . ')',
        );
        $this->addSql("UPDATE form_field_config SET visible = false WHERE field = 'asignaciones' AND entity_config_id = " . self::USUARIO);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE collection_field_config SET field = 'asignaciones', label = CASE WHEN label = 'roles' THEN 'asignaciones' ELSE label END "
            . "WHERE field = 'rolesVigentes' AND list_config_id IN (SELECT id FROM list_configuration WHERE entity_config_id = " . self::USUARIO . ')',
        );
        $this->addSql("UPDATE form_field_config SET visible = true WHERE field = 'asignaciones' AND entity_config_id = " . self::USUARIO);
    }
}
