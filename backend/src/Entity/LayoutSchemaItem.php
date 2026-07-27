<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Repository\LayoutSchemaItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LayoutSchemaItemRepository::class)]
#[ApiResourceNoPagination]
class LayoutSchemaItem extends Base
{
    #[ORM\ManyToOne(targetEntity: LayoutSchema::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LayoutSchema $layoutSchema = null;

    #[ORM\ManyToOne(targetEntity: VueRoute::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?VueRoute $vueRoute = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    public function getLayoutSchema(): ?LayoutSchema
    {
        return $this->layoutSchema;
    }

    public function setLayoutSchema(?LayoutSchema $layoutSchema): static
    {
        $this->layoutSchema = $layoutSchema;

        return $this;
    }

    public function getVueRoute(): ?VueRoute
    {
        return $this->vueRoute;
    }

    public function setVueRoute(?VueRoute $vueRoute): static
    {
        $this->vueRoute = $vueRoute;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
