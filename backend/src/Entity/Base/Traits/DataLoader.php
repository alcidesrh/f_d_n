<?php

namespace App\Entity\Base\Traits;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;

trait DataLoader {
  /** Se arma una vez por proceso: el listado de columnas lo llama por cada fila. */
  private static ?Inflector $inflector = null;

  /**
   * @param array $data
   *
   * @return $this
   */
  public function loadData(array $data) {
    $inflector = self::$inflector ??= InflectorFactory::createForLanguage(Language::SPANISH)->build();
    foreach ($data as $key => $value) {
      $method = $inflector->camelize('set_' . $key);
      if (\method_exists($this, $method)) {
        $this->$method($value);
      }
    }

    return $this;
  }
}
