<?php

namespace App\Entity;

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
            "collectionFieldConfig.position" => "ASC",
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
                    "collectionFieldConfig.position" => "ASC",
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
                    "collectionFieldConfig.position" => "ASC",
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
                    "collectionFieldConfig" => [
                        "type" => "[updateCollectionFieldConfigInput]",
                    ],
                    "listOptions" => [
                        "type" => "Iterable",
                        "description" =>
                            "Opciones del listado (ver EntityConfiguration::LIST_OPTIONS)",
                    ],
                ],
            ),
        ],
    ),
]
class EntityConfiguration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    public string $entityClass;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[
        ORM\OneToMany(
            mappedBy: "entityConfig",
            targetEntity: CollectionFieldConfig::class,
            cascade: ["persist", "remove"],
            orphanRemoval: true,
            fetch: "LAZY",
        ),
    ]
    #[ORM\OrderBy(["position" => "ASC"])]
    #[Groups(["read:dto"])]
    private Collection $collectionFieldConfig;

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

    /**
     * Opciones del listado de la entidad (no de una columna). Claves admitidas
     * y su tipo; lo demás se descarta al guardar. Null o ausente: el valor por
     * defecto del frontend.
     *
     * - `pageSize`: filas por página al abrir o restablecer el listado.
     * - `pageSizes`: opciones del selector de filas por página.
     * - `density`: `compact` | `normal` | `comfortable` (alto de fila).
     * - `filterMode`: `or` (cualquier filtro) | `and` (todos).
     * - `selectable`: ofrecer el modo selección.
     * - `inlineEdit`: permitir la edición en línea.
     */
    public const LIST_OPTIONS = [
        "pageSize" => "int",
        "pageSizes" => "int[]",
        "density" => ["compact", "normal", "comfortable"],
        "filterMode" => ["or", "and"],
        "selectable" => "bool",
        "inlineEdit" => "bool",
    ];

    #[ORM\Column(type: "json", nullable: true)]
    #[Groups(["read:dto"])]
    private ?array $listOptions = null;

    public function __construct(string $entityClass)
    {
        $this->entityClass = $entityClass;
        $this->collectionFieldConfig = new ArrayCollection();
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, CollectionFieldConfig>
     */
    public function getCollectionFieldConfig(): Collection
    {
        return $this->collectionFieldConfig;
    }

    public function setCollectionFieldConfig(
        Collection $collectionFieldConfig,
    ): Collection {
        return $this->collectionFieldConfig = $collectionFieldConfig;
    }

    public function addCollectionFieldConfig(
        CollectionFieldConfig $collectionFieldConfig,
    ): self {
        if (!$this->collectionFieldConfig->contains($collectionFieldConfig)) {
            $this->collectionFieldConfig->add($collectionFieldConfig);
            $collectionFieldConfig->setEntityConfig($this);
        }
        return $this;
    }

    public function removeCollectionFieldConfig(
        CollectionFieldConfig $collectionFieldConfig,
    ): self {
        if (
            $this->collectionFieldConfig->removeElement($collectionFieldConfig)
        ) {
            if ($collectionFieldConfig->getEntityConfig() === $this) {
                $collectionFieldConfig->setEntityConfig(null);
            }
        }
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

    public function getListOptions(): ?array
    {
        return $this->listOptions;
    }

    /** Guarda solo las claves de `LIST_OPTIONS` con un valor válido. */
    public function setListOptions(?array $options): static
    {
        $limpias = [];
        foreach (self::LIST_OPTIONS as $clave => $tipo) {
            $valor = $options[$clave] ?? null;
            $valido = match (true) {
                $valor === null => false,
                \is_array($tipo) => \in_array($valor, $tipo, true),
                $tipo === "int" => \is_int($valor) && $valor > 0 && $valor <= 500,
                $tipo === "bool" => \is_bool($valor),
                $tipo === "int[]" => \is_array($valor)
                    && $valor !== []
                    && array_is_list($valor)
                    && array_filter($valor, static fn ($n) => !\is_int($n) || $n <= 0 || $n > 500) === [],
                default => false,
            };
            if ($valido) {
                $limpias[$clave] = $valor;
            }
        }
        $this->listOptions = $limpias === [] ? null : $limpias;

        return $this;
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
