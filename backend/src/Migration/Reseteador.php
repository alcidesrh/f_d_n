<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Reinicia los datos de la base de datos nueva.
 *
 * - hard(): DROP SCHEMA public CASCADE + recreate + SchemaTool (esquema completo).
 * - soft(): Limpiador → truncado de tablas migrables e IAM, sin tocar el esquema.
 * - conservando(): como soft(), pero conserva lo que no depende del legado
 *   (menús, rutas del frontend, íconos, IAM, configuración; Limpiador::CONSERVABLES).
 *
 * Tras vaciar la BD (incluida la tabla `usuario`), ambos modos regeneran el
 * usuario de respaldo `admin` (ROLE_SUPER_ADMIN) vía AsegurarUsuarioAdmin.
 *
 * Extraído de MigrarTodoCommand::resetDB para que el panel /migracion
 * ofrezca ambos modos sin alterar el comportamiento del comando CLI.
 */
final class Reseteador
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Limpiador $limpiador,
        private readonly AsegurarUsuarioAdmin $asegurarUsuarioAdmin,
        private readonly CoordenadasEnclaves $coordenadas,
    ) {}

    public function hard(): void
    {
        $this->coordenadas->guardar();
        $conn = $this->em->getConnection();
        $conn->executeStatement("DROP SCHEMA public CASCADE");
        $conn->executeStatement("CREATE SCHEMA public");
        $conn->executeStatement("GRANT ALL ON SCHEMA public TO PUBLIC");

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->createSchema($metadata);

        $this->asegurarUsuarioAdmin->asegurar();
    }

    public function soft(): void
    {
        $this->coordenadas->guardar();
        $this->limpiador->limpiar();
        $this->asegurarUsuarioAdmin->asegurar();
    }

    /** @return array{conservadas: list<string>, vaciadas: list<string>} */
    public function conservando(): array
    {
        $this->coordenadas->guardar();
        $resultado = $this->limpiador->limpiarConservando();
        $this->asegurarUsuarioAdmin->asegurar();

        return $resultado;
    }
}
