<?php

namespace App\Entity\Base\Traits;

use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;

trait DataLoader {
  /**
   * @param array $data
   *
   * @return $this
   */
  public function loadData(array $data) {
    $inflector = InflectorFactory::createForLanguage(Language::SPANISH)->build();
    foreach ($data as $key => $value) {
      $method = $inflector->camelize('set_' . $key);
      if (\method_exists($this, $method)) {
        $this->$method($value);
      }
    }

    return $this;
  }
}
