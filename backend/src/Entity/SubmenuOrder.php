<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\SubmenuOrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubmenuOrderRepository::class)]
#[ApiResource]
class SubmenuOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $parent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $child = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $position = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?Menu
    {
        return $this->parent;
    }

    public function setParent(?Menu $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getChild(): ?Menu
    {
        return $this->child;
    }

    public function setChild(?Menu $child): static
    {
        $this->child = $child;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
