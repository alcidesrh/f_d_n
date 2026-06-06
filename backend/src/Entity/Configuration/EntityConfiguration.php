<?php

namespace App\Entity\Configuration;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\QueryParameter;
use App\Attribute\ApiResourceNoPagination;
use App\Repository\EntityConfigurationRepository;
use App\Resolver\UpdateEntityConfigurationFieldsResolver;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: EntityConfigurationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
  order: ['collectionFieldConfig.position' => 'ASC', 'formFields.position' => 'ASC'],
  graphQlOperations: [
    new Query(name: 'item_query'),
    new QueryCollection(
      paginationEnabled: false,
      parameters: [
        'entityClass' => new QueryParameter(
          filter: new ExactFilter(),
          property: 'entityClass'
        ),
      ],
    ),
    new QueryCollection(
      name: 'get',
      order: ['collectionFieldConfig.position' => 'ASC', 'formFields.position' => 'ASC'],
      normalizationContext: ['groups' => ['read:dto']],
      paginationEnabled: false,
      parameters: [
        'entityClass' => new QueryParameter(
          filter: new ExactFilter(),
          property: 'entityClass'
        ),
      ],
    ),
    new Mutation(name: 'update'),
    new Mutation(
      name: 'updateWithRelations',
      resolver: UpdateEntityConfigurationFieldsResolver::class,
      read: true,          // Carga automáticamente la entidad
      deserialize: false,  // Evita la deserialización automática
      validate: false,
      args: [
        'entityClass' => [
          'type' => 'String!',
          'description' => 'IRI o ID de la entidad (ej: "/api/entity_configurations/97")'
        ],
        'formFields' => [
          'type' => '[updateFormFieldConfigInput]',   // JSON como string
        ],
        'collectionFieldConfig' => [
          'type' => '[updateCollectionFieldConfigInput]',
        ]
      ]
    )
  ],
  operations: [
    new GetCollection(
      normalizationContext: ['groups' => ['read:dto']],
      order: ['collectionFieldConfig.position' => 'ASC', 'formFields.position' => 'ASC'],
      paginationEnabled: false,
      parameters: [
        'entityClass' => new QueryParameter(
          filter: new ExactFilter(),
          property: 'entityClass'
        ),
      ],
    ),
  ]
)]
class EntityConfiguration {
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 255, unique: true)]
  public string $entityClass;

  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  private ?\DateTimeImmutable $updatedAt = null;

  #[ORM\OneToMany(mappedBy: 'entityConfig', targetEntity: CollectionFieldConfig::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'LAZY')]
  #[Groups(['read:dto'])]
  private Collection $collectionFieldConfig;

  #[ORM\OneToMany(mappedBy: 'entityConfig', targetEntity: FormFieldConfig::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
  #[Groups(['read:dto'])]
  private Collection $formFields;


  public function __construct(string $entityClass) {
    $this->entityClass = $entityClass;
    $this->collectionFieldConfig = new ArrayCollection();
    $this->formFields = new ArrayCollection();
    $this->updatedAt = new \DateTimeImmutable();
  }

  #[ORM\PrePersist]
  #[ORM\PreUpdate]
  public function markAsUpdated(): void {
    $this->updatedAt = new \DateTimeImmutable();
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function setEntityClass($entityClass): self {
    $this->entityClass = $entityClass;
    return $this;
  }

  public function getEntityClass(): string {
    return $this->entityClass;
  }

  public function getUpdatedAt(): ?\DateTimeImmutable {
    return $this->updatedAt;
  }

  /**
   * @return Collection<int, CollectionFieldConfig>
   */
  public function getCollectionFieldConfig(): Collection {
    return $this->collectionFieldConfig;
  }

  public function addCollectionFieldConfig(CollectionFieldConfig $collectionFieldConfig): self {
    if (!$this->collectionFieldConfig->contains($collectionFieldConfig)) {
      $this->collectionFieldConfig->add($collectionFieldConfig);
      $collectionFieldConfig->setEntityConfig($this);
    }
    return $this;
  }

  public function removeCollectionFieldConfig(CollectionFieldConfig $collectionFieldConfig): self {
    if ($this->collectionFieldConfig->removeElement($collectionFieldConfig)) {
      if ($collectionFieldConfig->getEntityConfig() === $this) {
        $collectionFieldConfig->setEntityConfig(null);
      }
    }
    return $this;
  }

  /**
   * @return Collection<int, FormFieldConfig>
   */
  public function getFormFields(): Collection {
    return $this->formFields;
  }

  public function addFormField(FormFieldConfig $formField): self {
    if (!$this->formFields->contains($formField)) {
      $this->formFields->add($formField);
      $formField->setEntityConfig($this);
    }
    return $this;
  }

  public function removeFormField(FormFieldConfig $formField): self {
    if ($this->formFields->removeElement('$formField')) {
      if ($formField->getEntityConfig() === $this) {
        $formField->setEntityConfig(null);
      }
    }
    return $this;
  }
  public function orderFields(Collection $data): void {
    $position = 1;
    foreach ($data as $field) {
      if (!$field->isVisible()) continue;
      $field->setPosition($position++);
    }
    foreach ($data as $field) {
      if ($field->isVisible()) continue;
      $field->setPosition($position++);
    }
  }
}
