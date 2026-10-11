<?php

declare(strict_types=1);

namespace App\Doctrine\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\SchemaManagerFactory;

/** Usa `ExclusionConstraintsSchemaManager` en PostgreSQL; el resto, el de siempre. */
final class ExclusionConstraintsSchemaManagerFactory implements SchemaManagerFactory
{
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        $platform = $connection->getDatabasePlatform();

        return $platform instanceof PostgreSQLPlatform
            ? new ExclusionConstraintsSchemaManager($connection, $platform)
            : (new DefaultSchemaManagerFactory())->createSchemaManager($connection);
    }
}
