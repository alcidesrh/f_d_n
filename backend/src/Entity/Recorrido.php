<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Entity\Enum\EstadoRecorrido;
use App\Repository\RecorridoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecorridoRepository::class)]
#[ORM\Index(columns: ["fecha", "empresa_id", "trayecto_id"], name: "idx_recorrido_fecha_empresa_trayecto")]
#[ApiResourcePaginationPage]
class Recorrido extends Base
{
    use TimestampableEntityTrait;

    #[ORM\Column]
    private ?\DateTime $fecha = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $legacyId = null;

    #[ORM\ManyToOne]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    private ?Bus $bus = null;

    #[ORM\Column(type: "string", length: 20, enumType: EstadoRecorrido::class)]
    private EstadoRecorrido $estado = EstadoRecorrido::PROGRAMADA;

    /**
     * @var Collection<int, BoletoAsiento>
     */
    #[ORM\OneToMany(targetEntity: BoletoAsiento::class, mappedBy: "recorrido")]
    private Collection $boletoAsientos;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trayecto $trayecto = null;

    public function __construct()
    {
        $this->boletoAsientos = new ArrayCollection();
    }

    public function getFecha(): ?\DateTime
    {
        return $this->fecha;
    }

    public function setFecha(\DateTime $fecha): static
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getBus(): ?Bus
    {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static
    {
        $this->bus = $bus;

        return $this;
    }

    public function getEstado(): EstadoRecorrido
    {
        return $this->estado;
    }

    /**
     * Transiciona el recorrido al nuevo estado, validando la máquina de estados:
     * programada -> abordando -> iniciada -> finalizada (lineal),
     * y programada -> cancelada (solo desde programada).
     *
     * @throws \DomainException si la transición no está permitida
     */
    public function setEstado(EstadoRecorrido $estado): static
    {
        if (
            $estado !== $this->estado &&
            !$this->estado->puedeTransicionarA($estado)
        ) {
            throw new \DomainException(
                sprintf(
                    "Transición de Recorrido inválida: %s -> %s",
                    $this->estado->value,
                    $estado->value,
                ),
            );
        }

        $this->estado = $estado;

        return $this;
    }

    public function getLegacyId(): ?string
    {
        return $this->legacyId;
    }

    public function setLegacyId(?string $legacyId): static
    {
        $this->legacyId = $legacyId;
        return $this;
    }

    /**
     * @return Collection<int, BoletoAsiento>
     */
    public function getBoletoAsientos(): Collection
    {
        return $this->boletoAsientos;
    }

    public function addBoletoAsiento(BoletoAsiento $boletoAsiento): static
    {
        if (!$this->boletoAsientos->contains($boletoAsiento)) {
            $this->boletoAsientos->add($boletoAsiento);
            $boletoAsiento->setRecorrido($this);
        }

        return $this;
    }

    public function removeBoletoAsiento(BoletoAsiento $boletoAsiento): static
    {
        if ($this->boletoAsientos->removeElement($boletoAsiento)) {
            // set the owning side to null (unless already changed)
            if ($boletoAsiento->getRecorrido() === $this) {
                $boletoAsiento->setRecorrido(null);
            }
        }

        return $this;
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
}
