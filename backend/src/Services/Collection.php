<?php


namespace App\Services;

use App\FormKit\Input;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use ReflectionFunction;

class Collection extends ArrayCollection {

  public function __construct(array|string $elements = []) {

    if (!\is_array($elements)) {
      $elements = [$elements];
    }

    parent::__construct($elements);
  }

  public function findFirstKeyAndValue(Closure $p) {
    foreach ($this->getValues() as $key => $element) {
      if ($p($key, $element)) {
        return ['key' => $key, 'value' => $element];
      }
    }

    return null;
  }


  protected function createFrom(array $elements) {
    return new static($elements);
  }

  public function clear(): self {
    parent::clear();
    return $this;
  }
  public function merge($v): self {

    foreach ($v as $k => $value) {
      $this->set($k, $value);
    }
    return $this;
  }
  public function value($v): self {
    $this->clear();
    if (!\is_array($v)) {
      $v = [$v];
    }
    foreach ($v as $value) {
      $this->add($value);
    }
    return $this;
  }

  public function each(Closure $p): self {
    $cant = (new ReflectionFunction($p))->getNumberOfParameters();
    if ($cant == 1) {
      $this->forAll(function ($k, $v) use ($p) {
        $p($v);
        return true;
      });
    } else if ($cant == 2) {
      $this->forAll(function ($k, $v) use ($p) {
        $p($k, $v);
        return true;
      });
    }
    return $this;
  }

  public function getPropertyByName($name) {
    return $this->findFirst(
      fn($k, $v) => $v->getName() == $name
    );
  }
}
