<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RecorridoMatrioskaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecorridoMatrioskaRepository::class)]
#[ApiResource]
class RecorridoMatrioska
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'subrecorridos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recorrido $recorrido = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recorrido $subrecorridos = null;

    #[ORM\Column(nullable: true)]
    private ?int $posicion = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecorrido(): ?Recorrido
    {
        return $this->recorrido;
    }

    public function setRecorrido(?Recorrido $recorrido): static
    {
        $this->recorrido = $recorrido;

        return $this;
    }

    public function getSubrecorridos(): ?Recorrido
    {
        return $this->subrecorridos;
    }

    public function setSubrecorridos(?Recorrido $subrecorridos): static
    {
        $this->subrecorridos = $subrecorridos;

        return $this;
    }

    public function getPosicion(): ?int
    {
        return $this->posicion;
    }

    public function setPosicion(?int $posicion): static
    {
        $this->posicion = $posicion;

        return $this;
    }
}
