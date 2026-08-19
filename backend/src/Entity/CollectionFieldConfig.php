<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourceNoPagination;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResourceNoPagination()]
class CollectionFieldConfig  extends FieldConfig
{

    #[ApiProperty(readable: false)]
    #[ORM\ManyToOne(inversedBy: 'collectionFieldConfig')]
    public EntityConfiguration $entityConfig;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:dto'])]
    private ?bool $sortable = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:dto'])]
    private ?bool $filterable = null;


    public function __construct(array $data)
    {
        $this->setData($data);
    }

    public function setData(array $data)
    {
        $this->setSortable(false)->setFilterable(false)->setField($data[0])->setVisible(true)->setLabel($data[0])->setAttrs(null);
        $this->kind = match ($data[1]) {
            'select', 'multiple', 'simple_array' => 'list',
            'datetime', 'date' => 'date',
            default => 'scalar'
        };

        if (\in_array($data[0], ['legacyId', 'apiTokens'])) {
            $this->visible = false;
        }
    }


    public function getEntityConfig(): EntityConfiguration
    {
        return $this->entityConfig;
    }

    public function setEntityConfig(EntityConfiguration $entityConfig): static
    {
        $this->entityConfig = $entityConfig;

        return $this;
    }

    public function isSortable(): ?bool
    {
        return $this->sortable;
    }

    public function setSortable(?bool $sortable): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function isFilterable(): ?bool
    {
        return $this->filterable;
    }

    public function setFilterable(?bool $filterable): static
    {
        $this->filterable = $filterable;

        return $this;
    }

}
