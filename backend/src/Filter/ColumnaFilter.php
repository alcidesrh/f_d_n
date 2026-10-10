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
 * - relación: `campo`, IRI o id del registro (a uno: igual; a muchos: lo contiene),
 *   o `campo_list` con varios (cualquiera de ellos);
 * - `id`: IRI o número.
 *
 * Los filtros de varias columnas se cumplen todos (AND), salvo con
 * `_combinar: "or"`: basta con uno. El rango de una fecha cuenta como un solo
 * filtro (desde y hasta se cumplen juntos).
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

    /** Argumento que elige cómo se combinan los filtros de distintas columnas. */
    public const COMBINAR = '_combinar';

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
            if ($tipo === 'a_uno' || $tipo === 'a_muchos') {
                $description["{$nombre}[]"] = ['property' => $campo, 'type' => 'string', 'required' => false];
            }
        }
        if ($description !== []) {
            $description[self::COMBINAR] = [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => '"or": basta con que se cumpla un filtro; si no, todos.',
            ];
        }

        return $description;
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $filtros = $context['filters'] ?? [];
        $alias = $queryBuilder->getRootAliases()[0];
        $parametros = $operation?->getParameters();

        /** @var array<string, list<string>> $condiciones campo → condiciones (todas se cumplen) */
        $condiciones = [];
        foreach ($this->argumentos($resourceClass) as $nombre => ['tipo' => $tipo, 'campo' => $campo]) {
            $valor = $filtros[$nombre] ?? null;
            if ($valor === null || $valor === '' || $valor === [] || $parametros?->has($nombre)) {
                continue;
            }
            if (\is_array($valor) && $tipo !== 'a_uno' && $tipo !== 'a_muchos') {
                continue;
            }

            $condicion = $this->condicion($queryBuilder, $queryNameGenerator, $tipo, "$alias.$campo", $campo, $valor);
            if ($condicion !== null) {
                $condiciones[$campo][] = $condicion;
            }
        }

        $grupos = array_map(
            static fn (array $partes) => \count($partes) === 1 ? $partes[0] : '('.implode(' AND ', $partes).')',
            array_values($condiciones),
        );
        if ($grupos === []) {
            return;
        }
        if (\count($grupos) > 1 && ($filtros[self::COMBINAR] ?? null) === 'or') {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$grupos));

            return;
        }
        foreach ($grupos as $grupo) {
            $queryBuilder->andWhere($grupo);
        }
    }

    /** DQL de un filtro (con sus parámetros ya puestos); null si el valor no sirve. */
    private function condicion(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $tipo, string $columna, string $campo, mixed $valor): ?string
    {
        if (\is_array($valor)) {
            $partes = [];
            foreach (array_values(array_unique(array_map('strval', $valor))) as $uno) {
                $parte = $this->condicion($queryBuilder, $queryNameGenerator, $tipo, $columna, $campo, $uno);
                if ($parte !== null) {
                    $partes[] = $parte;
                }
            }

            return match (\count($partes)) {
                0 => null,
                1 => $partes[0],
                default => '('.implode(' OR ', $partes).')',
            };
        }

        $p = $queryNameGenerator->generateParameterName($campo);

        switch ($tipo) {
            case 'texto':
                $queryBuilder->setParameter($p, '%'.addcslashes(mb_strtolower((string) $valor), '%_\\').'%');

                return "LOWER($columna) LIKE :$p";
            case 'entero':
            case 'decimal':
                $queryBuilder->setParameter($p, $valor);

                return "$columna = :$p";
            case 'booleano':
                $queryBuilder->setParameter($p, filter_var($valor, \FILTER_VALIDATE_BOOL));

                return "$columna = :$p";
            case 'desde':
                return $this->fecha($queryBuilder, "$columna >= :$p", $p, (string) $valor);
            case 'hasta':
                return $this->fecha($queryBuilder, "$columna < :$p", $p, (string) $valor, '+1 day');
            case 'id':
                return $this->id($queryBuilder, "$columna = :$p", $p, $valor);
            case 'a_uno':
                return $this->id($queryBuilder, "IDENTITY($columna) = :$p", $p, $valor);
            case 'a_muchos':
                return $this->id($queryBuilder, ":$p MEMBER OF $columna", $p, $valor);
        }

        return null;
    }

    private function fecha(QueryBuilder $queryBuilder, string $condicion, string $p, string $valor, string $mas = '+0 day'): ?string
    {
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($valor, 0, 10));
        if ($fecha === false) {
            return null;
        }
        $queryBuilder->setParameter($p, $fecha->modify($mas));

        return $condicion;
    }

    /** IRI (`/api/buses/12`) o número; cualquier otra cosa no encuentra nada. */
    private function id(QueryBuilder $queryBuilder, string $condicion, string $p, mixed $valor): string
    {
        if (preg_match('~(?:^|/)(-?\d+)$~', (string) $valor, $m) !== 1) {
            return '1 = 0';
        }
        $queryBuilder->setParameter($p, (int) $m[1]);

        return $condicion;
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
