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

    /**
     * Ancho de la columna en el listado: una longitud CSS (`12rem`, `160px`)
     * o un preset (`xs`, `sm`, `md`, `lg`, `xl`). Null: el listado lo decide.
     */
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['read:dto'])]
    private ?string $width = null;

    /**
     * `sortable`/`filterable` nacen en null ("lo que permita la API"): el
     * listado ordena o filtra si la colección GraphQL acepta el campo. Un
     * `false` explícito lo apaga.
     */
    public function __construct(array $data)
    {
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

}
