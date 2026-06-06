<?php

namespace App\GraphQL\Type\Converter;

use ApiPlatform\GraphQl\Type\TypeConverterInterface;
use ApiPlatform\GraphQl\Type\TypesContainerInterface;
use ApiPlatform\Metadata\GraphQl\Operation;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use GraphQL\Type\Definition\Type as GraphQLType;
use Symfony\Component\TypeInfo\Type;


final class DateTypeConverter implements TypeConverterInterface {
  public function __construct(
    private TypeConverterInterface $decorated,
    private TypesContainerInterface $types
  ) {
  }

  /**
   * ESTE es el método que realmente usa 4.2
   */


  public function convertPhpType(
    Type $type,
    bool $input,
    Operation $rootOperation,
    string $resourceClass,
    string $rootResource,
    ?string $property,
    int $depth
  ): GraphQLType|string|null {

    if ($property !== null && $resourceClass) {

      try {
        $reflection = new \ReflectionProperty($rootResource, $property);

        $reflectionType = $reflection->getType();

        if ($reflectionType instanceof \ReflectionNamedType) {
          if (is_a($reflectionType->getName(), \DateTimeInterface::class, true)) {
            return $this->types->get('Date');
          } else   if (is_a($reflectionType->getName(), Collection::class, true)) {
            // return $this->types->get('Multiple');
          }
        }
        // $reflection = new \ReflectionProperty($rootResource, $property);
        // $reflectionType = $reflection->getType();

        // if ($reflectionType instanceof \ReflectionNamedType) {
        //   $typeName = $reflectionType->getName();

        //   if (is_a($typeName, \DateTimeInterface::class, true)) {
        //     return $this->types->get('Date');
        //   }
        // }
      } catch (\ReflectionException $e) {
        return $this->decorated->convertPhpType(
          $type,
          $input,
          $rootOperation,
          $resourceClass,
          $rootResource,
          $property,
          $depth
        );
      }

      $attributes = $reflection->getAttributes(Column::class);

      if ($attributes) {
        $column = $attributes[0]->newInstance();

        if (
          $column->type === Types::DATE_MUTABLE ||
          $column->type === Types::DATE_IMMUTABLE
        ) {
          return $this->types->get('DateOnly');
        }
      }
    }

    return $this->decorated->convertPhpType(
      $type,
      $input,
      $rootOperation,
      $resourceClass,
      $rootResource,
      $property,
      $depth
    );
  }

  public function resolveType(string $type): ?GraphQLType {
    return $this->decorated->resolveType($type);
  }

  // Este queda solo porque la interfaz lo exige
  public function convertType(
    \Symfony\Component\PropertyInfo\Type $type,
    bool $input,
    Operation $rootOperation,
    string $resourceClass,
    string $rootResource,
    ?string $property,
    int $depth
  ): GraphQLType|string|null {
    if ($property !== null) {

      $reflection = new \ReflectionProperty($resourceClass, $property);
      $attributes = $reflection->getAttributes(\Doctrine\ORM\Mapping\Column::class);

      if ($attributes) {
        $column = $attributes[0]->newInstance();

        if (
          $column->type === Types::DATE_MUTABLE ||
          $column->type === Types::DATE_IMMUTABLE
        ) {
          return $this->types->get('DateOnly');
        }
      }
    }
    return null;
  }
}
