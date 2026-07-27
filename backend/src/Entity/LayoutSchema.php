<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\NombreNotaStatusBase;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Entity\Enum\LayoutArea;
use App\Repository\LayoutSchemaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LayoutSchemaRepository::class)]
#[ApiResourceNoPagination]
class LayoutSchema extends NombreNotaStatusBase
{
    use TimestampableEntityTrait;

    #[ORM\Column(type: 'string', enumType: LayoutArea::class)]
    private LayoutArea $area;

    /**
     * @var Collection<int, LayoutSchemaItem>
     */
    #[ORM\OneToMany(targetEntity: LayoutSchemaItem::class, mappedBy: 'layoutSchema', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        parent::__construct();
        $this->items = new ArrayCollection();
    }

    public function getArea(): LayoutArea
    {
        return $this->area;
    }

    public function setArea(LayoutArea $area): static
    {
        $this->area = $area;

        return $this;
    }

    /**
     * @return Collection<int, LayoutSchemaItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(LayoutSchemaItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setLayoutSchema($this);
        }

        return $this;
    }

    public function removeItem(LayoutSchemaItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getLayoutSchema() === $this) {
                $item->setLayoutSchema(null);
            }
        }

        return $this;
    }
}
