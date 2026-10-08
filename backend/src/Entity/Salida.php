<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Entity\Enum\EstadoSalida;
use App\Repository\SalidaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalidaRepository::class)]
#[ORM\Index(columns: ["fecha", "empresa_id", "trayecto_id"], name: "idx_salida_fecha_empresa_trayecto")]
#[ApiResourcePaginationPage]
class Salida extends Base
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

    #[ORM\Column(type: "string", length: 20, enumType: EstadoSalida::class)]
    private EstadoSalida $estado = EstadoSalida::PROGRAMADA;

    /**
     * @var Collection<int, BoletoAsiento>
     */
    #[ORM\OneToMany(targetEntity: BoletoAsiento::class, mappedBy: "salida")]
    private Collection $boletoAsientos;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trayecto $trayecto = null;

    /** Quien programó la salida; nulo en las migradas del legado (no lo guardaba). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Usuario $createdBy = null;

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

    public function getEstado(): EstadoSalida
    {
        return $this->estado;
    }

    /**
     * Transiciona el salida al nuevo estado, validando la máquina de estados:
     * programada -> abordando -> iniciada -> finalizada (lineal),
     * y programada -> cancelada (solo desde programada).
     *
     * @throws \DomainException si la transición no está permitida
     */
    public function setEstado(EstadoSalida $estado): static
    {
        if (
            $estado !== $this->estado &&
            !$this->estado->puedeTransicionarA($estado)
        ) {
            throw new \DomainException(
                sprintf(
                    "Transición de Salida inválida: %s -> %s",
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
            $boletoAsiento->setSalida($this);
        }

        return $this;
    }

    public function removeBoletoAsiento(BoletoAsiento $boletoAsiento): static
    {
        if ($this->boletoAsientos->removeElement($boletoAsiento)) {
            // set the owning side to null (unless already changed)
            if ($boletoAsiento->getSalida() === $this) {
                $boletoAsiento->setSalida(null);
            }
        }

        return $this;
    }

    public function getCreatedBy(): ?Usuario
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Usuario $createdBy): static
    {
        $this->createdBy = $createdBy;

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
