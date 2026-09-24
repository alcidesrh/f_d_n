<?php

namespace App\Attribute;

use ApiPlatform\Metadata\GraphQl\QueryCollection;

/** Recurso cuya colección devuelve todos los registros (catálogos pequeños). */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class ApiResourceNoPagination extends ApiResourceBase
{
    protected static function collection(): QueryCollection
    {
        return new QueryCollection(paginationEnabled: false);
    }
}
