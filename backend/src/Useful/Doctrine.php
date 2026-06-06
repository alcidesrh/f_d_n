<?php

namespace App\Useful;

use function Symfony\Component\String\u;

class Doctrine {

  public static function entityNamespace(string $className): string {

    return "App\Entity\\" . u($className)->camel()->title();
  }
}
