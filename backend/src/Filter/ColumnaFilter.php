<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\PropertyNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Filtro por columna de las colecciones del listado genérico (`/lista/:entity`):
 * un argumento por cada campo mapeado y legible en la API, para que el filtro
 * se resuelva en la base de datos y no sobre la página cargada.
 *
 * - texto (y enums): `campo`, contiene, sin distinguir mayúsculas;
 * - número: `campo`, igual; booleano: `campo`, igual;
 * - fecha: `campo_after` / `campo_before` (`yyyy-mm-dd`, ambos inclusive);
 * - relación: `campo`, IRI o id del registro (a uno: igual; a muchos: lo contiene);
 * - `id`: IRI o número.
 *
 * Los campos con `ApiProperty(readable: false)` (contraseñas, tokens…) no se
 * filtran: sería un oráculo para adivinarlos. Si la operación ya declara un
 * parámetro con el mismo nombre, manda el parámetro.
 */
final class ColumnaFilter implements FilterInterface
{
    private const TEXTO = [Types::STRING, Types::TEXT, Types::ASCII_STRING, Types::ENUM];
    private const ENTERO = [Types::INTEGER, Types::SMALLINT, Types::BIGINT];
    private const DECIMAL = [Types::FLOAT, Types::DECIMAL, Types::SMALLFLOAT];
    private const FECHA = [
        Types::DATE_MUTABLE, Types::DATE_IMMUTABLE,
        Types::DATETIME_MUTABLE, Types::DATETIME_IMMUTABLE,
        Types::DATETIMETZ_MUTABLE, Types::DATETIMETZ_IMMUTABLE,
    ];

    /** @var array<class-string, array<string, array{tipo: string, campo: string}>> */
    private array $cache = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly PropertyMetadataFactoryInterface $propertyMetadata,
    ) {
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];
        foreach ($this->argumentos($resourceClass) as $nombre => ['tipo' => $tipo, 'campo' => $campo]) {
            $description[$nombre] = [
                'property' => $campo,
                'type' => match ($tipo) {
                    'entero' => 'int',
                    'decimal' => 'float',
                    'booleano' => 'bool',
                    default => 'string',
                },
                'required' => false,
            ];
        }

        return $description;
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $filtros = $context['filters'] ?? [];
        $alias = $queryBuilder->getRootAliases()[0];
        $parametros = $operation?->getParameters();

        foreach ($this->argumentos($resourceClass) as $nombre => ['tipo' => $tipo, 'campo' => $campo]) {
            $valor = $filtros[$nombre] ?? null;
            if ($valor === null || $valor === '' || \is_array($valor) || $parametros?->has($nombre)) {
                continue;
            }

            $p = $queryNameGenerator->generateParameterName($campo);
            $columna = "$alias.$campo";

            match ($tipo) {
                'texto' => $queryBuilder
                    ->andWhere("LOWER($columna) LIKE :$p")
                    ->setParameter($p, '%'.addcslashes(mb_strtolower((string) $valor), '%_\\').'%'),
                'entero', 'decimal' => $queryBuilder->andWhere("$columna = :$p")->setParameter($p, $valor),
                'booleano' => $queryBuilder->andWhere("$columna = :$p")->setParameter($p, filter_var($valor, \FILTER_VALIDATE_BOOL)),
                'desde' => $this->fecha($queryBuilder, "$columna >= :$p", $p, (string) $valor),
                'hasta' => $this->fecha($queryBuilder, "$columna < :$p", $p, (string) $valor, '+1 day'),
                'id' => $this->id($queryBuilder, "$columna = :$p", $p, $valor),
                'a_uno' => $this->id($queryBuilder, "IDENTITY($columna) = :$p", $p, $valor),
                'a_muchos' => $this->id($queryBuilder, ":$p MEMBER OF $columna", $p, $valor),
            };
        }
    }

    private function fecha(QueryBuilder $queryBuilder, string $condicion, string $p, string $valor, string $mas = '+0 day'): void
    {
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($valor, 0, 10));
        if ($fecha === false) {
            return;
        }
        $queryBuilder->andWhere($condicion)->setParameter($p, $fecha->modify($mas));
    }

    /** IRI (`/api/buses/12`) o número; cualquier otra cosa no encuentra nada. */
    private function id(QueryBuilder $queryBuilder, string $condicion, string $p, mixed $valor): void
    {
        if (preg_match('~(?:^|/)(-?\d+)$~', (string) $valor, $m) !== 1) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }
        $queryBuilder->andWhere($condicion)->setParameter($p, (int) $m[1]);
    }

    /** @return array<string, array{tipo: string, campo: string}> nombre del argumento → tipo y campo */
    private function argumentos(string $resourceClass): array
    {
        if (isset($this->cache[$resourceClass])) {
            return $this->cache[$resourceClass];
        }

        $manager = $this->registry->getManagerForClass($resourceClass);
        if ($manager === null) {
            return $this->cache[$resourceClass] = [];
        }
        /** @var ClassMetadata $metadata */
        $metadata = $manager->getClassMetadata($resourceClass);

        $argumentos = [];
        foreach ($metadata->getFieldNames() as $campo) {
            if (str_contains($campo, '.') || !$this->legible($resourceClass, $campo)) {
                continue;
            }
            $tipo = $metadata->getTypeOfField($campo);
            $mapping = $metadata->getFieldMapping($campo);
            if ($metadata->isIdentifier($campo)) {
                $argumentos[$campo] = ['tipo' => 'id', 'campo' => $campo];
            } elseif (\in_array($tipo, self::TEXTO, true) || ($mapping->enumType ?? null) !== null) {
                $argumentos[$campo] = ['tipo' => 'texto', 'campo' => $campo];
            } elseif (\in_array($tipo, self::ENTERO, true)) {
                $argumentos[$campo] = ['tipo' => 'entero', 'campo' => $campo];
            } elseif (\in_array($tipo, self::DECIMAL, true)) {
                $argumentos[$campo] = ['tipo' => 'decimal', 'campo' => $campo];
            } elseif ($tipo === Types::BOOLEAN) {
                $argumentos[$campo] = ['tipo' => 'booleano', 'campo' => $campo];
            } elseif (\in_array($tipo, self::FECHA, true)) {
                $argumentos["{$campo}_after"] = ['tipo' => 'desde', 'campo' => $campo];
                $argumentos["{$campo}_before"] = ['tipo' => 'hasta', 'campo' => $campo];
            }
        }

        foreach ($metadata->getAssociationNames() as $campo) {
            if (!$this->legible($resourceClass, $campo)) {
                continue;
            }
            if ($metadata->isCollectionValuedAssociation($campo)) {
                $argumentos[$campo] = ['tipo' => 'a_muchos', 'campo' => $campo];
            } elseif ($metadata->isAssociationWithSingleJoinColumn($campo)) {
                $argumentos[$campo] = ['tipo' => 'a_uno', 'campo' => $campo];
            }
        }

        return $this->cache[$resourceClass] = $argumentos;
    }

    private function legible(string $resourceClass, string $campo): bool
    {
        try {
            return $this->propertyMetadata->create($resourceClass, $campo)->isReadable() !== false;
        } catch (PropertyNotFoundException) {
            return false;
        }
    }
}
