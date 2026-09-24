<?php

namespace App\Attribute;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Operations;

/**
 * Recurso API de una entidad con el CRUD GraphQL completo: item, colección
 * (definida por cada variante), create, update y delete. `graphQlOperations`
 * añade operaciones propias (p. ej. una colección con filtros por parámetro).
 */
abstract class ApiResourceBase extends ApiResource
{
    public function __construct(?array $graphQlOperations = null, ?Operations $operations = null, mixed ...$data)
    {
        parent::__construct(
            ...static::defaults($data),
            graphQlOperations: [
                new Query(),
                new Mutation(name: 'create'),
                new Mutation(name: 'update'),
                new DeleteMutation(name: 'delete'),
                static::collection(),
                ...($graphQlOperations ?? []),
            ],
            operations: $operations,
        );
    }

    /** Operación de colección GraphQL de la variante. */
    abstract protected static function collection(): QueryCollection;

    /** Argumentos de `ApiResource` que fija la variante. */
    protected static function defaults(array $data): array
    {
        return $data;
    }
}
