<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourceNoPagination;
use App\Metadata\ConfiguracionDelCliente;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResourceNoPagination()]
class CollectionFieldConfig  extends FieldConfig implements ConfiguracionDelCliente
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

    /**
     * Ancho de la columna en el listado: una longitud CSS (`12rem`, `160px`)
     * o un preset (`xs`, `sm`, `md`, `lg`, `xl`). Null: el listado lo decide.
     */
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['read:dto'])]
    private ?string $width = null;

    /**
     * Relación a una entidad con muchos registros (cientos o miles, p. ej.
     * `BoletoTarifa.trayecto`): su filtro es un autocompletado que busca en el
     * servidor mientras se teclea, en lugar de cargar todos los registros.
     * Solo tiene efecto en columnas de relación filtrables.
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:dto'])]
    private ?bool $autocomplete = null;

    /**
     * `sortable`/`filterable` nacen apagados: ordenar o filtrar por una
     * columna se habilita explícitamente (null y false significan lo mismo).
     */
    public function __construct(array $data)
    {
        $this->setSortable(false)->setFilterable(false);
        $this->setField($data[0])->setVisible(true)->setLabel($data[0])->setAttrs(null);
        $this->setData($data);
        if (\in_array($data[0], ['legacyId', 'apiTokens'])) {
            $this->visible = false;
        }
    }

    /**
     * Lo que se deriva del mapeo de Doctrine (el tipo). Lo demás es de quien
     * configura la entidad: sincronizar no lo pisa.
     */
    public function setData(array $data)
    {
        $this->kind = match ($data[1]) {
            'select', 'multiple', 'simple_array' => 'list',
            'datetime', 'date' => 'date',
            default => 'scalar'
        };
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

    public function entidadConfigurada(): ?string
    {
        return isset($this->entityConfig) ? $this->entityConfig->getEntityClass() : null;
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

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function setWidth(?string $width): static
    {
        $width = $width === null ? null : trim($width);
        $this->width = $width === '' ? null : $width;

        return $this;
    }

    public function isAutocomplete(): ?bool
    {
        return $this->autocomplete;
    }

    public function setAutocomplete(?bool $autocomplete): static
    {
        $this->autocomplete = $autocomplete;

        return $this;
    }
}
