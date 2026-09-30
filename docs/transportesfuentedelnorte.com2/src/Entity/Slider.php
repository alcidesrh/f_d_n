<?php

namespace App\Entity;

use App\Repository\SliderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SliderRepository::class)]
class Slider {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToMany(targetEntity: Archivo::class)]
    #[ORM\OrderBy(["id" => "ASC"])]
    private Collection $images;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nombre = null;

    public function __construct() {
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @return Collection<int, Archivo>
     */
    public function getImages(): Collection {
        return $this->images;
    }

    public function addImage(Archivo $image): self {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
        }

        return $this;
    }

    public function removeImage(Archivo $image): self {
        $this->images->removeElement($image);

        return $this;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): self {
        $this->nombre = $nombre;

        return $this;
    }
}
