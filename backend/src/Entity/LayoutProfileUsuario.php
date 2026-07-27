<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Repository\LayoutProfileUsuarioRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LayoutProfileUsuarioRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_profile_usuario', columns: ['layout_profile_id', 'usuario_id'])]
#[ApiResourceNoPagination]
class LayoutProfileUsuario extends Base
{
    #[ORM\ManyToOne(targetEntity: LayoutProfile::class, inversedBy: 'usuarioAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LayoutProfile $layoutProfile = null;

    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Usuario $usuario = null;

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

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): static
    {
        $this->usuario = $usuario;

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
