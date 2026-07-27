<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\NombreNotaStatusBase;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Repository\LayoutProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LayoutProfileRepository::class)]
#[ApiResourceNoPagination]
class LayoutProfile extends NombreNotaStatusBase
{
    use TimestampableEntityTrait;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\ManyToOne(targetEntity: LayoutSchema::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?LayoutSchema $layoutSchema = null;

    /**
     * @var Collection<int, LayoutProfileRole>
     */
    #[ORM\OneToMany(targetEntity: LayoutProfileRole::class, mappedBy: 'layoutProfile', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $roleAssignments;

    /**
     * @var Collection<int, LayoutProfileUsuario>
     */
    #[ORM\OneToMany(targetEntity: LayoutProfileUsuario::class, mappedBy: 'layoutProfile', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $usuarioAssignments;

    public function __construct()
    {
        parent::__construct();
        $this->roleAssignments = new ArrayCollection();
        $this->usuarioAssignments = new ArrayCollection();
    }

    /**
     * Fusiona perfiles de configuración de layout.
     * Las propiedades de $p (usuario) prevalecen sobre las de $p2 (roles) si coinciden en el área de layout.
     *
     * @param self $p Perfil primario
     * @param self|self[] $p2 Perfil(es) secundario(s)
     * @return self[] Perfiles resultantes indexados por el área de layout (string)
     */
    public static function merge(self $p, self|array $p2): array
    {
        $secondaryProfiles = is_array($p2) ? $p2 : [$p2];
        $merged = [];

        foreach ($secondaryProfiles as $profile) {
            if (!$profile->isEnabled() || !$profile->getLayoutSchema()) {
                continue;
            }
            $area = $profile->getLayoutSchema()->getArea()->value;
            $merged[$area] = $profile;
        }

        if ($p->isEnabled() && $p->getLayoutSchema()) {
            $area = $p->getLayoutSchema()->getArea()->value;
            $merged[$area] = $p;
        }

        return $merged;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLayoutSchema(): ?LayoutSchema
    {
        return $this->layoutSchema;
    }

    public function setLayoutSchema(?LayoutSchema $layoutSchema): static
    {
        $this->layoutSchema = $layoutSchema;

        return $this;
    }

    /**
     * @return Collection<int, LayoutProfileRole>
     */
    public function getRoleAssignments(): Collection
    {
        return $this->roleAssignments;
    }

    public function addRoleAssignment(LayoutProfileRole $roleAssignment): static
    {
        if (!$this->roleAssignments->contains($roleAssignment)) {
            $this->roleAssignments->add($roleAssignment);
            $roleAssignment->setLayoutProfile($this);
        }

        return $this;
    }

    public function removeRoleAssignment(LayoutProfileRole $roleAssignment): static
    {
        if ($this->roleAssignments->removeElement($roleAssignment)) {
            if ($roleAssignment->getLayoutProfile() === $this) {
                $roleAssignment->setLayoutProfile(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LayoutProfileUsuario>
     */
    public function getUsuarioAssignments(): Collection
    {
        return $this->usuarioAssignments;
    }

    public function addUsuarioAssignment(LayoutProfileUsuario $usuarioAssignment): static
    {
        if (!$this->usuarioAssignments->contains($usuarioAssignment)) {
            $this->usuarioAssignments->add($usuarioAssignment);
            $usuarioAssignment->setLayoutProfile($this);
        }

        return $this;
    }

    public function removeUsuarioAssignment(LayoutProfileUsuario $usuarioAssignment): static
    {
        if ($this->usuarioAssignments->removeElement($usuarioAssignment)) {
            if ($usuarioAssignment->getLayoutProfile() === $this) {
                $usuarioAssignment->setLayoutProfile(null);
            }
        }

        return $this;
    }
}
