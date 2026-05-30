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
class FormFieldConfig extends FieldConfig {

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['read:dto'])]
  public ?string $groupName = null;

  #[ApiProperty(readable: false)]
  #[ORM\ManyToOne(inversedBy: 'formFields')]
  public EntityConfiguration $entityConfig;

  public function __construct(array $data) {
    $this->setData($data);
  }

  public function setData(array $data) {
    $this->setField($data[0])->setVisible(true)
      ->setGroupName(null)->setAttrs(null)->setLabel($data[0]);
    if (\in_array($data[0], ['legacyId', 'apiTokens', 'id', 'createdAt', 'updatedAt'])) {
      $this->visible = false;
    }
  }
  public function getGroupName(): ?string {
    return $this->groupName;
  }

  public function setGroupName(?string $groupName): self {
    $this->groupName = $groupName;
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
