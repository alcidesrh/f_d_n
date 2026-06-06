<?php

namespace App\Attribute;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Operations;
use Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class ApiResourcePaginationPage extends ApiResourceBase {

    public function __construct(protected ?array $graphQlOperations = null, protected ?Operations $operations = null, ...$data) {
        $default = [
            new QueryCollection(
                paginationType: 'page',
                filters: ['order.filter'],
            ),
            ...($graphQlOperations ?? []),
        ];



        if ($operations) {
            parent::__construct(...$data, graphQlOperations: $default, operations: new Operations((array)($operations)));
        } else {
            parent::__construct(...$data, graphQlOperations: $default);
        }
    }
}
