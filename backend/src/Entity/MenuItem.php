<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ítem navegable: texto, ícono y ruta del frontend. Se crea de antemano y
 * luego se coloca en uno o varios `Menu` (cada menú decide su jerarquía y
 * orden en su propia `Taxonomy`, por eso `position` no vive aquí).
 *
 * El texto es `nombre`; `label` es derivado de solo lectura, como en todas
 * las entidades.
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
class MenuItem extends Base
{
    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Icon $icon = null;

    /** Si la ruta desaparece del router (sincronización), el ítem se borra con ella. */
    #[Assert\NotNull]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?VueRoute $route = null;

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

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

    public function getRoute(): ?VueRoute
    {
        return $this->route;
    }

    public function setRoute(?VueRoute $route): static
    {
        $this->route = $route;

        return $this;
    }
}
