<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * `BIGINT` en la base, texto en PHP y en la API.
 *
 * Con `bigint` a secas, Doctrine lo expone como `int|string` y API Platform
 * lo publica en GraphQL como `Int` (32 bits): los números de DTE de más de
 * 2 147 483 647 hacen fallar la consulta. Este tipo mantiene la misma columna
 * (sin migración) y deja el valor como string.
 */
final class BigintTextoType extends Type
{
    public const NAME = 'bigint_texto';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBigIntTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return false;
    }
}
