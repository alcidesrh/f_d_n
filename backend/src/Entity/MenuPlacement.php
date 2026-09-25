<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\LayoutArea;
use Doctrine\ORM\Mapping as ORM;

/**
 * Colocación de un `Menu` en un área de la UI (`LayoutArea`, uno por slot
 * `menu-content` del shell) con su orden dentro del área. Se gestiona entera
 * por área desde `App\Navigation\MenuLayout`.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_menu_placement', columns: ['area', 'menu_id'])]
class MenuPlacement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Menu $menu;

    #[ORM\Column(length: 30, enumType: LayoutArea::class)]
    private LayoutArea $area;

    #[ORM\Column]
    private int $position;

    public function __construct(Menu $menu, LayoutArea $area, int $position)
    {
        $this->menu = $menu;
        $this->area = $area;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function getArea(): LayoutArea
    {
        return $this->area;
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
}
