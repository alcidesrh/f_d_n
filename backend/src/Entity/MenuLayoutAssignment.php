<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Entity\Enum\LayoutArea;
use App\Repository\MenuLayoutAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuLayoutAssignmentRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_MENU_AREA', columns: ['menu_id', 'layout_area'])]
#[ApiResourceNoPagination]
class MenuLayoutAssignment extends Base
{
    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'layoutAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Menu $menu = null;

    #[ORM\Column(type: 'string', enumType: LayoutArea::class)]
    private LayoutArea $layoutArea;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getLayoutArea(): LayoutArea
    {
        return $this->layoutArea;
    }

    public function setLayoutArea(LayoutArea $layoutArea): static
    {
        $this->layoutArea = $layoutArea;

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
}
