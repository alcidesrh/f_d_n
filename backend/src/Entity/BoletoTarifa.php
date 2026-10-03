<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Embeddable\Precio;
use App\Entity\Enum\AsientoClase;
use App\Repository\BoletoTarifaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

/**
 * Precio de un asiento. Fija algunos de empresa, trayecto, horario, clase de
 * bus, bus y clase de asiento (obligatoria); los demás son comodín. Rige desde
 * `vigenteDesde`: una tarifa nueva con fecha futura no aplica hasta entonces.
 * Elección: `App\Venta\EspecificidadTarifa` (ADR-021).
 */
#[ORM\Entity(repositoryClass: BoletoTarifaRepository::class)]
#[ApiResourcePaginationPage]
class BoletoTarifa extends Base
{
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Embedded(class: Precio::class)]
    private ?Precio $precio = null;

    #[ORM\ManyToOne]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    private ?Bus $bus = null;

    #[ORM\ManyToOne]
    private ?Trayecto $trayecto = null;

    #[ORM\ManyToOne]
    private ?BusClase $busClase = null;

    /**
     * Horario en que parte la salida (desde el origen de su trayecto), con
     * los extremos incluidos. Si `horaDesde` > `horaHasta` cruza la medianoche
     * (22:15–04:00). Un extremo vacío deja ese lado abierto.
     */
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $horaDesde = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $horaHasta = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $vigenteDesde;

    #[ORM\Column(type: "string", length: 1, enumType: AsientoClase::class)]
    private AsientoClase $clase;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    public function __construct()
    {
        $this->vigenteDesde = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getPrecio(): ?Money
    {
        return $this->precio?->toMoney();
    }

    public function setPrecio(Money $money): self
    {
        $this->precio = Precio::fromMoney($money);
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

    public function getTrayecto(): ?Trayecto
    {
        return $this->trayecto;
    }

    public function setTrayecto(?Trayecto $trayecto): static
    {
        $this->trayecto = $trayecto;

        return $this;
    }

    public function getBusClase(): ?BusClase
    {
        return $this->busClase;
    }

    public function setBusClase(?BusClase $busClase): static
    {
        $this->busClase = $busClase;

        return $this;
    }

    public function getHoraDesde(): ?\DateTime
    {
        return $this->horaDesde;
    }

    public function setHoraDesde(?\DateTime $horaDesde): static
    {
        $this->horaDesde = $horaDesde;

        return $this;
    }

    public function getHoraHasta(): ?\DateTime
    {
        return $this->horaHasta;
    }

    public function setHoraHasta(?\DateTime $horaHasta): static
    {
        $this->horaHasta = $horaHasta;

        return $this;
    }

    public function getVigenteDesde(): \DateTime
    {
        return $this->vigenteDesde;
    }

    public function setVigenteDesde(\DateTime $vigenteDesde): static
    {
        $this->vigenteDesde = $vigenteDesde;

        return $this;
    }

    public function getClase(): AsientoClase
    {
        return $this->clase;
    }

    public function setClase(AsientoClase $clase): static
    {
        $this->clase = $clase;

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
}
