<?php

declare(strict_types=1);

namespace App\Entity\Base\Traits;


trait HydrateFromArrayTrait {
  /**
   * Asigna valores de un array asociativo a las propiedades de la clase.
   * 
   * - Primero intenta llamar al setter correspondiente (`setNombrePropiedad`).
   * - Si no existe, asigna directamente la propiedad (funciona con protected y private).
   * - Ignora claves que no correspondan a ninguna propiedad.
   *
   * @param array<string, mixed> $data Array asociativo (clave = nombre de la propiedad)
   * @return self
   */
  public function setFromArray(array $data): self {
    foreach ($data as $property => $value) {
      $setter = 'set' . ucfirst($property);

      if (method_exists($this, $setter)) {
        $this->{$setter}($value);
      } elseif (property_exists($this, $property)) {
        $this->{$property} = $value;
      }
      // Si la propiedad no existe, se ignora silenciosamente (comportamiento seguro)
    }

    return $this;
  }
}
