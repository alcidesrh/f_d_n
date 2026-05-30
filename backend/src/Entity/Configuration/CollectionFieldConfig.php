<?php

namespace App\Entity\Configuration;

use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Attribute\ApiResourceNoPagination;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResourceNoPagination(
  graphQlOperations: [
    new QueryCollection(
      paginationEnabled: false,
      order: ['position' => 'ASC'],
    ),
  ]
)]
class CollectionFieldConfig  extends FieldConfig {

  #[ApiProperty(readable: false)]
  #[ORM\ManyToOne(inversedBy: 'collectionFieldConfig')]
  public EntityConfiguration $entityConfig;

  #[ORM\Column(nullable: true)]
  #[Groups(['read:dto'])]
  private bool $sortable = false;

  #[ORM\Column(nullable: true)]
  #[Groups(['read:dto'])]
  private bool $filterable = false;

  public function __construct(array $data) {
    $this->setData($data);
  }

  public function setData(array $data) {
    $this->setField($data[0])->setVisible(true)
      ->setSortable(false)->setLabel($data[0])->setAttrs(null);
    if (\in_array($data[0], ['legacyId', 'apiTokens'])) {
      $this->visible = false;
    }
  }

  public function isSortable(): bool {
    return $this->sortable;
  }

  public function setSortable(bool $sortable): self {
    $this->sortable = $sortable;
    return $this;
  }

  public function isFilterable(): bool {
    return $this->filterable;
  }

  public function setFilterable(bool $filterable): self {
    $this->filterable = $filterable;
    return $this;
  }

  public function getEntityConfig(): EntityConfiguration {
    return $this->entityConfig;
  }

  public function setEntityConfig(EntityConfiguration $entityConfig): static {
    $this->entityConfig = $entityConfig;

    return $this;
  }
}
