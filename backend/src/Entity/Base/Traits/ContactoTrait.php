<?php

namespace App\Entity\Base\Traits;

use App\Attribute\FormMetadataAttribute;
use App\Entity\Localidad;
use Doctrine\ORM\Mapping as ORM;

trait ContactoTrait {

  #[ORM\Column(length: 255)]
  protected ?string $nombre = null;

  #[Assert\Email(message: 'The email {{ value }} is not a valid email.')]
  #[ORM\Column(length: 50, nullable: true)]
  protected ?string $email = null;

  #[ORM\Column(length: 20, nullable: true)]
  protected ?string $nit = null;

  #[ORM\Column(length: 15, nullable: true)]
  protected ?string $telefono = null;

  #[ORM\Column(length: 255, nullable: true)]
  protected ?string $direccion = null;

  #[ORM\ManyToOne]
  protected ?Localidad $localidad = null;

  public function getNombre(): ?string {
    return $this->nombre;
  }

  public function setNombre(string $nombre): static {
    $this->nombre = $nombre;

    return $this;
  }

  public function getEmail(): ?string {
    return $this->email;
  }

  public function setEmail(?string $email): static {
    $this->email = $email;

    return $this;
  }
  public function getTelefono(): ?string {
    return $this->telefono;
  }

  public function setTelefono(?string $telefono): static {
    $this->telefono = $telefono;

    return $this;
  }

  public function getNit(): ?string {
    return $this->nit;
  }

  public function setNit(?string $nit): static {
    $this->nit = $nit;

    return $this;
  }
  public function getDireccion(): ?string {
    return $this->direccion;
  }

  public function setDireccion(?string $direccion): static {
    $this->direccion = $direccion;

    return $this;
  }
  public function getLocalidad(): ?Localidad {
    return $this->localidad;
  }

  public function setLocalidad(?Localidad $localidad): static {
    $this->localidad = $localidad;

    return $this;
  }
}
