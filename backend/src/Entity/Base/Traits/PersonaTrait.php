<?php

namespace App\Entity\Base\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

trait PersonaTrait {

  use ContactoTrait;

  #[ORM\Column(length: 50, nullable: true)]
  protected ?string $apellido = null;

  public function getApellido(): ?string {
    return $this->apellido;
  }

  public function setApellido(?string $apellido): static {
    $this->apellido = $apellido;

    return $this;
  }
}
