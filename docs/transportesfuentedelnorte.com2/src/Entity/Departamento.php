<?php

namespace App\Entity;

use App\Repository\DepartamentoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: DepartamentoRepository::class)]
#[ORM\Cache(usage: "NONSTRICT_READ_WRITE")]
class Departamento
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private ?string $nombre = null;

    #[ORM\OneToMany(mappedBy: 'departamento', targetEntity: Estacion::class)]
    private Collection $estaciones;

    #[ORM\Column(length: 110, unique: true)]
    #[Gedmo\Slug(fields: ['nombre'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $prioridad = null;

    #[ORM\Column(nullable: true)]
    private ?bool $activo = null;

    public function __construct()
    {
        $this->estaciones = new ArrayCollection();
        $this->prioridad = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * @return Collection<int, Estacion>
     */
    public function getEstaciones(): Collection
    {
        return $this->estaciones;
    }

    public function addEstacion(Estacion $estacion): self
    {
        if (!$this->estaciones->contains($estacion)) {
            $this->estaciones->add($estacion);
            $estacion->setDepartamento($this);
        }

        return $this;
    }

    public function removeEstacion(Estacion $estacion): self
    {
        if ($this->estaciones->removeElement($estacion)) {
            // set the owning side to null (unless already changed)
            if ($estacion->getDepartamento() === $this) {
                $estacion->setDepartamento(null);
            }
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPrioridad(): ?int
    {
        return $this->prioridad;
    }

    public function setPrioridad(?int $prioridad): self
    {
        $this->prioridad = $prioridad;

        return $this;
    }

    public function isActivo(): ?bool
    {
        return $this->activo;
    }

    public function setActivo(?bool $activo): self
    {
        $this->activo = $activo;

        return $this;
    }
}
