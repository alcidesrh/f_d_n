<?php

namespace App\Entity;

use App\Acceso\Recurso;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\QueryParameter;
use App\ApiResource\EntityConfigurationByEntityClassProvider;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Icon;
use App\Metadata\ConfiguracionDelCliente;
use App\Repository\EntityConfigurationRepository;
use App\Resolver\UpdateEntityConfigurationFieldsResolver;
use Symfony\Component\Serializer\Attribute\Groups; // Carga automáticamente la entidad
// Evita la deserialización automática
// JSON como string

#[ORM\Entity(repositoryClass: EntityConfigurationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[
    ApiResource(
        order: [
            "formFields.position" => "ASC",
        ],
        operations: [
            new Get(requirements: ["id" => "\d+"]),
            new Get(
                normalizationContext: ["groups" => ["read:dto"]],
                name: "refresh",
                uriTemplate: "/entity_configurations/refresh",
                provider: EntityConfigurationByEntityClassProvider::class,
            ),
            new GetCollection(
                normalizationContext: ["groups" => ["read:dto"]],
                order: [
                    "formFields.position" => "ASC",
                ],
                paginationEnabled: false,
                parameters: [
                    "entityClass" => new QueryParameter(
                        filter: new ExactFilter(),
                        property: "entityClass",
                    ),
                ],
            ),
        ],
        graphQlOperations: [
            new Query(name: "item_query"),
            new QueryCollection(
                paginationEnabled: false,
                parameters: [
                    "entityClass" => new QueryParameter(
                        filter: new ExactFilter(),
                        property: "entityClass",
                    ),
                ],
            ),
            new QueryCollection(
                name: "get",
                order: [
                    "formFields.position" => "ASC",
                ],
                normalizationContext: ["groups" => ["read:dto"]],
                paginationEnabled: false,
                parameters: [
                    "entityClass" => new QueryParameter(
                        filter: new ExactFilter(),
                        property: "entityClass",
                    ),
                ],
            ),
            new Mutation(name: "update"),
            new Mutation(
                name: "updateWithRelations",
                resolver: UpdateEntityConfigurationFieldsResolver::class,
                read: true,
                deserialize: false,
                validate: false,
                args: [
                    "entityClass" => [
                        "type" => "String!",
                        "description" =>
                            'IRI o ID de la entidad (ej: "/api/entity_configurations/97")',
                    ],
                    "formFields" => [
                        "type" => "[updateFormFieldConfigInput]",
                    ],
                ],
            ),
        ],
    ),
]
#[Recurso(nombre: "Configuración de entidades", area: "Configuración", lecturaLibre: true, editar: "config.editar", eliminar: "config.editar")]
class EntityConfiguration implements ConfiguracionDelCliente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    public string $entityClass;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Configuraciones del listado (ADR-043): la por defecto (sin roles) y las
     * de cada grupo de roles, por prioridad.
     *
     * @var Collection<int, ListConfiguration>
     */
    #[ORM\OneToMany(mappedBy: "entityConfig", targetEntity: ListConfiguration::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["position" => "ASC", "id" => "ASC"])]
    private Collection $listConfigurations;

    #[
        ORM\OneToMany(
            mappedBy: "entityConfig",
            targetEntity: FormFieldConfig::class,
            cascade: ["persist", "remove"],
            orphanRemoval: true,
        ),
    ]
    #[ORM\OrderBy(["position" => "ASC"])]
    #[Groups(["read:dto"])]
    private Collection $formFields;

    #[ORM\ManyToOne]
    private ?Icon $icon = null;

    public function __construct(string $entityClass)
    {
        $this->entityClass = $entityClass;
        $this->listConfigurations = new ArrayCollection();
        $this->formFields = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function markAsUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setEntityClass($entityClass): self
    {
        $this->entityClass = $entityClass;
        return $this;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function entidadConfigurada(): ?string
    {
        return $this->entityClass;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ListConfiguration> */
    public function getListConfigurations(): Collection
    {
        return $this->listConfigurations;
    }

    public function addListConfiguration(ListConfiguration $config): self
    {
        if (!$this->listConfigurations->contains($config)) {
            $this->listConfigurations->add($config);
        }
        return $this;
    }

    public function removeListConfiguration(ListConfiguration $config): self
    {
        $this->listConfigurations->removeElement($config);
        return $this;
    }

    public function setFormFields(Collection $formFields): Collection
    {
        return $this->formFields = $formFields;
    }

    /**
     * @return Collection<int, FormFieldConfig>
     */
    public function getFormFields(): Collection
    {
        return $this->formFields;
    }

    public function addFormField(FormFieldConfig $formField): self
    {
        if (!$this->formFields->contains($formField)) {
            $this->formFields->add($formField);
            $formField->setEntityConfig($this);
        }
        return $this;
    }

    public function removeFormField(FormFieldConfig $formField): self
    {
        if ($this->formFields->removeElement('$formField')) {
            if ($formField->getEntityConfig() === $this) {
                $formField->setEntityConfig(null);
            }
        }
        return $this;
    }
    public function orderFields(Collection $data): void
    {
        $position = 1;
        foreach ($data as $field) {
            if (!$field->isVisible()) {
                continue;
            }
            $field->setPosition($position++);
        }
        foreach ($data as $field) {
            if ($field->isVisible()) {
                continue;
            }
            $field->setPosition($position++);
        }
    }

    public function getIcon(): ?Icon
    {
        return $this->icon;
    }

    public function setIcon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }
}
