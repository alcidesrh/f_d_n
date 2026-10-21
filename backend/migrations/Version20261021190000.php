<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La configuración de una entidad ya no tiene pantalla de elección: se abre
 * desde su listado (`/configuracion/entidades/{entidad}`). El enlace
 * "Configuración de entidades" de la administración apuntaba a la ruta sin
 * entidad, que se retiró; se quita junto con sus roles y usuarios (cascada).
 */
final class Version20261021190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Quita el enlace "Configuración de entidades" de la administración (la ruta sin entidad ya no existe)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM enlace_administracion WHERE ruta = 'entity-config'");
    }

    public function down(Schema $schema): void
    {
        // Vuelve el enlace a la caja "Sistema", al final. La ruta sin entidad ya no existe en el frontend.
        $this->addSql(
            "INSERT INTO enlace_administracion (seccion_id, etiqueta, icono, claves, ruta, parametros, posicion, activo)
             SELECT s.id, 'Configuración de entidades', 'tune', '[]', 'entity-config', '{}',
                    (SELECT COALESCE(MAX(e.posicion), 0) + 1 FROM enlace_administracion e WHERE e.seccion_id = s.id), true
             FROM seccion_administracion s WHERE s.titulo = 'Sistema' ORDER BY s.id DESC LIMIT 1",
        );
    }
}
