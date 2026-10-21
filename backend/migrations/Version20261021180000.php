<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Todo usuario tiene `ROLE_USER`: es el rol base al que se asignan los menús
 * y cajas de administración que ve cualquier empleado. Los nuevos lo reciben
 * al crearse (`App\Usuario\RolBaseUsuario`) y los del legado al migrar
 * (`MigradorIAM`).
 */
final class Version20261021180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Asigna ROLE_USER a todos los usuarios';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO role (nombre) SELECT 'ROLE_USER' WHERE NOT EXISTS (SELECT 1 FROM role WHERE nombre = 'ROLE_USER')");
        $this->addSql("INSERT INTO user_role (user_id, role_id) SELECT u.id, r.id FROM usuario u CROSS JOIN role r WHERE r.nombre = 'ROLE_USER' ON CONFLICT DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        // No se distingue a quién se le dio aquí de quién ya lo tenía.
    }
}
