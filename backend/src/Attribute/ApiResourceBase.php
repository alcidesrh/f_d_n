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
class ApiResourceBase extends ApiResource {

    public function __construct(protected ?array $graphQlOperations = null, protected ?Operations $operations = null, ...$data) {
        
        $default = [
            new Query(),
            new Mutation(name: 'create'),
            new Mutation(name: 'update'),
            new DeleteMutation(name: 'delete'),
            // new QueryCollection(
            //     name: 'list',
            //     paginationType: 'page',
            //     filters: ['order.filter'],
            // ),
            ...($graphQlOperations ?? []),
        ];
        foreach ($graphQlOperations as $key => $value) {
            if ($value instanceof QueryCollection && $value->getName() === 'list') {
                unset($default[4]);
                break;
            }
        }

        if ($operations) {
            parent::__construct(...$data, graphQlOperations: $default, operations: new Operations((array)($operations)));
        } else {
            parent::__construct(...$data, graphQlOperations: $default);
        }
    }
}
