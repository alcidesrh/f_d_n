<?php

namespace App\Entity;

use App\Repository\TarjetaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Cache(usage: 'READ_ONLY')]
#[ORM\Entity(repositoryClass: TarjetaRepository::class)]
class Tarjeta {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codigo = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $prioridad = null;

    #[ORM\ManyToOne(cascade: ['remove'])]
    #[ORM\JoinColumn(onDelete: 'set null')]
    private ?Archivo $imagen = null;

    #[ORM\Column(nullable: true)]
    private ?bool $activo = null;

    public function __construct() {
        $this->prioridad = 0;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self {
        $this->nombre = $nombre;

        return $this;
    }

    public function getCodigo(): ?string {
        return $this->codigo;
    }

    public function setCodigo(?string $codigo): self {
        $this->codigo = $codigo;

        return $this;
    }

    public function getPrioridad(): ?int {
        return $this->prioridad;
    }

    public function setPrioridad(?int $prioridad): self {
        $this->prioridad = $prioridad;

        return $this;
    }

    public function getImagen(): ?Archivo {
        return $this->imagen;
    }

    public function setImagen(?Archivo $imagen): self {
        $this->imagen = $imagen;

        return $this;
    }

    public function isActivo(): ?bool {
        return $this->activo;
    }

    public function setActivo(?bool $activo): self {
        $this->activo = $activo;

        return $this;
    }
}
