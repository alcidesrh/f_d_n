<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * @author Vincent Amstoutz <vincent.amstoutz.dev@gmail.com>
 */
final class IdPartialSearchFilter implements FilterInterface, OpenApiParameterFilterInterface {
  use BackwardCompatibleFilterDescriptionTrait;
  use OpenApiFilterTrait;

  public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void {
    $parameter = $context['parameter'];
    $property = $parameter->getProperty();
    $alias = $queryBuilder->getRootAliases()[0];
    $field = $alias . '.' . $property;
    $values = $parameter->getValue();
    $queryBuilder->{$context['whereClause'] ?? 'andWhere'}("CAST($field AS TEXT) LIKE '%$values%'");

    return;
  }
}
