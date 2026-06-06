<?php
// src/GraphQL/Type/DateOnlyType.php

namespace App\GraphQL\Type\Definition;

use ApiPlatform\GraphQl\Type\Definition\TypeInterface;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Error\Error;

final class DateType extends ScalarType implements TypeInterface {
  public string $name = 'Date';


  public function getName(): string {
    return $this->name;
  }

  public function serialize($value): ?string {
    // if (!$value instanceof \DateTimeInterface) {
    //   return null;
    // }
    return $value; //
  }

  public function parseValue($value): \DateTimeImmutable {
    return $this->parse($value);
  }

  public function parseLiteral($valueNode, ?array $variables = null): \DateTimeImmutable {
    if (!$valueNode instanceof StringValueNode) {
      throw new Error('DateOnly must be string YYYY-MM-DD');
    }

    return $this->parse($valueNode->value);
  }

  private function parse(string $value): \DateTimeImmutable {
    $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
      throw new Error('Invalid DateOnly format, expected YYYY-MM-DD');
    }

    return $date;
  }
}
