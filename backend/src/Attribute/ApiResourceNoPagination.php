<?php

namespace App\Attribute;

use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Operations;
use Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class ApiResourceNoPagination extends ApiResourceBase {

    public function __construct(protected ?array $graphQlOperations = null, ...$data) {

        $default = [
            new QueryCollection(
                paginationEnabled: false,
            ),
            ...($graphQlOperations ?? []),
        ];
        parent::__construct(...$data, graphQlOperations: $default);
    }
}
