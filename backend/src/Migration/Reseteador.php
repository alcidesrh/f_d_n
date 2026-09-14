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
 *
 * Extraído de MigrarTodoCommand::resetDB para que el panel /migracion
 * ofrezca ambos modos sin alterar el comportamiento del comando CLI.
 */
final class Reseteador
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Limpiador $limpiador,
    ) {}

    public function hard(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement("DROP SCHEMA public CASCADE");
        $conn->executeStatement("CREATE SCHEMA public");
        $conn->executeStatement("GRANT ALL ON SCHEMA public TO PUBLIC");

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->createSchema($metadata);
    }

    public function soft(): void
    {
        $this->limpiador->limpiar();
    }
}
