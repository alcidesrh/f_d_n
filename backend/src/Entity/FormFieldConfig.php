<?php

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourceNoPagination;
use App\Metadata\ConfiguracionDelCliente;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResourceNoPagination]
#[Recurso(nombre: "Campos de los formularios", area: "Configuración", lecturaLibre: true, editar: "config.editar", eliminar: "config.editar")]
class FormFieldConfig extends FieldConfig implements ConfiguracionDelCliente
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["read:dto"])]
    public ?string $groupName = null;

    #[ApiProperty(readable: false)]
    #[ORM\ManyToOne(inversedBy: "formFields")]
    public EntityConfiguration $entityConfig;

    public function __construct(array $data)
    {
        $this->setField($data[0])
            ->setVisible(true)
            ->setGroupName(null)
            ->setAttrs(null)
            ->setLabel($data[0]);
        if (
            \in_array($data[0], [
                "legacyId",
                "apiTokens",
                "id",
                "createdAt",
                "updatedAt",
            ])
        ) {
            $this->visible = false;
        }
    }

    /** Sincronizar con el mapeo no pisa lo que configuró el usuario. */
    public function setData(array $data)
    {
    }
    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(?string $groupName): self
    {
        $this->groupName = $groupName;
        return $this;
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
}
