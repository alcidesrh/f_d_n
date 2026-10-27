<?php

declare(strict_types=1);

namespace App\Entity;

use App\Metadata\ConfiguracionDelCliente;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Una configuración del listado de una entidad: sus columnas, sus opciones y
 * los roles que la ven. Una entidad tiene una o más; la que no tiene roles
 * es la por defecto (la ven los usuarios sin un rol asociado a otra). Un rol
 * está a lo sumo en una configuración por entidad. A quien tiene roles en
 * varias le toca la primera por `position` (`App\ConfiguracionListado`).
 *
 * Sin API Platform: se lee y se escribe por `/api/configuracion-listados` y
 * `/api/me/configuracion/{entidad}` (`ConfiguracionListadoController`).
 */
#[ORM\Entity]
#[ORM\Table(name: "list_configuration")]
#[ORM\Index(name: "IDX_LIST_CONFIGURATION_POSITION", columns: ["entity_config_id", "position"])]
class ListConfiguration implements ConfiguracionDelCliente
{
    /**
     * Opciones del listado (no de una columna). Claves admitidas y su tipo; lo
     * demás se descarta al guardar. Null o ausente: el valor por defecto del
     * frontend.
     *
     * - `pageSize`: filas por página al abrir o restablecer el listado.
     * - `pageSizes`: opciones del selector de filas por página.
     * - `density`: `compact` | `normal` | `comfortable` (alto de fila).
     * - `filterMode`: `or` (cualquier filtro) | `and` (todos).
     * - `selectable`: ofrecer el modo selección (botón "Seleccionar").
     * - `inlineEdit`: permitir la edición en línea.
     * - `create`: botón "Nuevo" sobre el listado.
     * - `edit`, `delete`: íconos "Editar" y "Eliminar" de cada fila.
     *
     * Ocultar un botón no quita el permiso: la API sigue decidiendo.
     */
    public const LIST_OPTIONS = [
        "pageSize" => "int",
        "pageSizes" => "int[]",
        "density" => ["compact", "normal", "comfortable"],
        "filterMode" => ["or", "and"],
        "selectable" => "bool",
        "inlineEdit" => "bool",
        "create" => "bool",
        "edit" => "bool",
        "delete" => "bool",
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "listConfigurations")]
    #[ORM\JoinColumn(name: "entity_config_id", nullable: false, onDelete: "CASCADE")]
    private EntityConfiguration $entityConfig;

    #[ORM\Column(length: 120)]
    private string $nombre;

    /** Prioridad entre las de la entidad (menor primero). */
    #[ORM\Column(options: ["default" => 0])]
    private int $position = 0;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: "list_configuration_role")]
    #[ORM\JoinColumn(name: "list_configuration_id", onDelete: "CASCADE")]
    #[ORM\InverseJoinColumn(name: "role_id", onDelete: "CASCADE")]
    private Collection $roles;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $listOptions = null;

    /** @var Collection<int, CollectionFieldConfig> */
    #[ORM\OneToMany(mappedBy: "listConfig", targetEntity: CollectionFieldConfig::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $collectionFieldConfig;

    public function __construct(EntityConfiguration $entityConfig, string $nombre, int $position = 0)
    {
        $this->entityConfig = $entityConfig;
        $this->nombre = $nombre;
        $this->position = $position;
        $this->roles = new ArrayCollection();
        $this->collectionFieldConfig = new ArrayCollection();
        $entityConfig->addListConfiguration($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityConfig(): EntityConfiguration
    {
        return $this->entityConfig;
    }

    public function entidadConfigurada(): ?string
    {
        return $this->entityConfig->getEntityClass();
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

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

    /** La que ven los usuarios sin un rol asociado a otra. */
    public function esPorDefecto(): bool
    {
        return $this->roles->isEmpty();
    }

    /** @return Collection<int, Role> */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /** @return list<int> */
    public function rolIds(): array
    {
        return array_values(array_map(static fn(Role $r) => (int) $r->getId(), $this->roles->toArray()));
    }

    /** @param iterable<Role> $roles */
    public function setRoles(iterable $roles): static
    {
        $this->roles->clear();
        foreach ($roles as $role) {
            if (!$this->roles->contains($role)) {
                $this->roles->add($role);
            }
        }

        return $this;
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
                    && array_filter($valor, static fn($n) => !\is_int($n) || $n <= 0 || $n > 500) === [],
                default => false,
            };
            if ($valido) {
                $limpias[$clave] = $valor;
            }
        }
        $this->listOptions = $limpias === [] ? null : $limpias;

        return $this;
    }

    /** @return Collection<int, CollectionFieldConfig> */
    public function getCollectionFieldConfig(): Collection
    {
        return $this->collectionFieldConfig;
    }

    public function addCollectionFieldConfig(CollectionFieldConfig $columna): static
    {
        if (!$this->collectionFieldConfig->contains($columna)) {
            $this->collectionFieldConfig->add($columna);
            $columna->setListConfig($this);
        }

        return $this;
    }

    /** Copia opciones y columnas de otra configuración (roles no: un rol solo está en una). */
    public function copiarDe(self $origen): static
    {
        $this->listOptions = $origen->listOptions;
        foreach ($origen->collectionFieldConfig as $columna) {
            $this->addCollectionFieldConfig($columna->copia());
        }

        return $this;
    }
}
