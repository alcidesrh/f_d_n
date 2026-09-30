<?php

namespace App\Entity;

use App\Repository\EstacionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: EstacionRepository::class)]
#[ORM\Cache(usage: "NONSTRICT_READ_WRITE")]
class Estacion {

    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $alias = null;

    #[ORM\ManyToOne(inversedBy: 'estaciones')]
    private ?Departamento $departamento = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(length: 110, unique: true)]
    #[Gedmo\Slug(fields: ['nombre'])]
    private ?string $slug = null;

    #[ORM\Column(nullable: true)]
    private ?bool $activa = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $prioridad = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $estacion_id = null;

    public function __construct() {
        $this->prioridad = 0;
        // $this->conexions = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function setId(int $id): self {
        $this->id = $id;

        return $this;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self {
        $this->nombre = $nombre;

        return $this;
    }

    public function getAlias(): ?string {
        return $this->alias;
    }

    public function setAlias(?string $alias): self {
        $this->alias = $alias;

        return $this;
    }

    public function getDepartamento(): ?Departamento {
        return $this->departamento;
    }

    public function setDepartamento(?Departamento $departamento): self {
        $this->departamento = $departamento;

        return $this;
    }

    public function getDireccion(): ?string {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): self {
        $this->direccion = $direccion;

        return $this;
    }

    public function getSlug(): ?string {
        return $this->slug;
    }

    public function setSlug(string $slug): self {
        $this->slug = $slug;

        return $this;
    }

    public function isActiva(): ?bool {
        return $this->activa;
    }

    public function setActiva(?bool $activa): self {
        $this->activa = $activa;

        return $this;
    }

    public function __toString() {
        return $this->nombre;
    }

    public function getPrioridad(): ?int {
        return $this->prioridad;
    }

    public function setPrioridad(?int $prioridad): self {
        $this->prioridad = $prioridad;

        return $this;
    }

    public function getEstacionId(): ?string {
        return $this->estacion_id;
    }

    public function setEstacionId(?string $estacion_id): static {
        $this->estacion_id = $estacion_id;

        return $this;
    }
}
