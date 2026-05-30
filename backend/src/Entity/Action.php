<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Attribute\CollectionMetadataAttribute;
use App\Attribute\FormMetadataAttribute;
use App\Entity\Base\Base;
use App\Entity\Base\Traits\StatusTrait;
use App\Repository\ActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActionRepository::class)]
#[ApiResourceNoPagination]
#[FormMetadataAttribute(order: ['nombre', 'ruta', 'roles'])]
#[CollectionMetadataAttribute(
    class: 'col-wraper',
    props: [
        ['name' => 'id', 'class' => ' col-small'],
        ['name' => 'nombre', 'class' => 'col-wraper'],
        ['name' => 'ruta'],
        ['name' => 'roles']
    ]
)]
class Action extends Base {

    use StatusTrait;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ruta = null;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $nombre = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'actions')]
    private Collection $roles;

    public function __construct() {

        parent::__construct();
        $this->roles = new ArrayCollection();
    }



    public function getId(): ?int {
        return $this->id;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }
    public function setNombre(string $nombre): static {
        $this->nombre = $nombre;

        return $this;
    }




    public function getRuta(): ?string {
        return $this->ruta;
    }

    public function setRuta(?string $ruta): static {
        $this->ruta = $ruta;

        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection {
        return $this->roles;
    }

    public function addRole(Role $role): static {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }

        return $this;
    }

    public function removeRole(Role $role): static {
        $this->roles->removeElement($role);

        return $this;
    }
}
