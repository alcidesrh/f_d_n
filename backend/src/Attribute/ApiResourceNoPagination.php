<?php

namespace App\Attribute;

use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Operations;
use Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class ApiResourceNoPagination extends ApiResourceBase
{

    public function __construct(protected ?array $graphQlOperations = null, protected ?Operations $operations = null, ...$data)
    {

        $default = [
            new QueryCollection(
                paginationEnabled: false,
            ),
            ...($graphQlOperations ?? []),
        ];
        parent::__construct(...$data, graphQlOperations: $default);
        if ($operations) {
            parent::__construct(...$data, graphQlOperations: $default, operations: new Operations((array)($operations)));
        } else {
            parent::__construct(...$data, graphQlOperations: $default);
        }
    }
}
