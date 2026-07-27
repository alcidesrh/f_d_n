<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Repository\LayoutProfileRoleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LayoutProfileRoleRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_profile_role', columns: ['layout_profile_id', 'role_id'])]
#[ApiResourceNoPagination]
class LayoutProfileRole extends Base
{
    #[ORM\ManyToOne(targetEntity: LayoutProfile::class, inversedBy: 'roleAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LayoutProfile $layoutProfile = null;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Role $role = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    public function getLayoutProfile(): ?LayoutProfile
    {
        return $this->layoutProfile;
    }

    public function setLayoutProfile(?LayoutProfile $layoutProfile): static
    {
        $this->layoutProfile = $layoutProfile;

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

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
