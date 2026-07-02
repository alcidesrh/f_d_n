<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Embeddable\Precio;
use App\Repository\RecorridoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity(repositoryClass: RecorridoRepository::class)]
#[ApiResourcePaginationPage]
class Recorrido extends Base {

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Embedded(class: Precio::class)]
    private ?Precio $precioClaseA = null;

    #[ORM\Embedded(class: Precio::class)]
    private ?Precio $precioClaseB = null;


    #[ORM\ManyToOne]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    private ?Bus $bus = null;

    #[ORM\ManyToOne(inversedBy: 'recorridos')]
    private ?Trayecto $trayecto = null;

    /**
     * @var Collection<int, RecorridoMatrioska>
     */
    #[ORM\OneToMany(targetEntity: RecorridoMatrioska::class, mappedBy: 'recorrido', orphanRemoval: true)]
    private Collection $subrecorridos;

    public function __construct()
    {
        $this->subrecorridos = new ArrayCollection();
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

    public function getPrecioClaseA(): ?Money {
        return $this->precioClaseA ? $this->precioClaseA->toMoney() : null;
    }

    public function setPrecioClaseA(Money $money): self {
        $this->precioClaseA = Precio::fromMoney($money);
        return $this;
    }

    public function getPrecioClaseB(): ?Money {
        return $this->precioClaseB ? $this->precioClaseB->toMoney() : null;
    }

    public function setPrecioClaseB(Money $money): self {
        $this->precioClaseB = Precio::fromMoney($money);
        return $this;
    }

    public function getEmpresa(): ?Empresa {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static {
        $this->empresa = $empresa;

        return $this;
    }

    public function getBus(): ?Bus {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static {
        $this->bus = $bus;

        return $this;
    }

    public function getRecorridos(): Collection {
        return $this->recorridos;
    }

    public function getTrayecto(): ?Trayecto
    {
        return $this->trayecto;
    }

    public function setTrayecto(?Trayecto $trayecto): static
    {
        $this->trayecto = $trayecto;

        return $this;
    }

    /**
     * @return Collection<int, RecorridoMatrioska>
     */
    public function getSubrecorridos(): Collection
    {
        return $this->subrecorridos;
    }

    public function addSubrecorrido(RecorridoMatrioska $subrecorrido): static
    {
        if (!$this->subrecorridos->contains($subrecorrido)) {
            $this->subrecorridos->add($subrecorrido);
            $subrecorrido->setRecorrido($this);
        }

        return $this;
    }

    public function removeSubrecorrido(RecorridoMatrioska $subrecorrido): static
    {
        if ($this->subrecorridos->removeElement($subrecorrido)) {
            // set the owning side to null (unless already changed)
            if ($subrecorrido->getRecorrido() === $this) {
                $subrecorrido->setRecorrido(null);
            }
        }

        return $this;
    }
}
