<?php

namespace App\Attribute;

use ApiPlatform\Metadata\GraphQl\QueryCollection;

/** Recurso con colección paginada por página (`currentPage`/`itemsPerPage`) y orden. */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class ApiResourcePaginationPage extends ApiResourceBase
{
    protected static function collection(): QueryCollection
    {
        return new QueryCollection(filters: ['order.filter']);
    }

    protected static function defaults(array $data): array
    {
        return ['paginationType' => 'page', ...$data];
    }
}
