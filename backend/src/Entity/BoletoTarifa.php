<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Embeddable\Precio;
use App\Entity\Enum\AsientoClase;
use App\Filter\BoletoTarifaFilter;
use App\Repository\BoletoTarifaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Currency;
use Money\Money;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Precio de un asiento. Fija algunos de empresa, trayecto, horario, clase de
 * bus, bus y clase de asiento (obligatoria); los demás son comodín. Rige desde
 * `vigenteDesde`: una tarifa nueva con fecha futura no aplica hasta entonces.
 * Elección: `App\Venta\EspecificidadTarifa` (ADR-021).
 *
 * El listado la busca además por los enclaves del trayecto y el horario
 * (`BoletoTarifaFilter`). `Money` no tiene tipo en GraphQL: la API lee el
 * precio como `importe` (formateado) y lo escribe como `precioMonto`.
 */
#[ORM\Entity(repositoryClass: BoletoTarifaRepository::class)]
#[ApiResourcePaginationPage]
#[ApiFilter(BoletoTarifaFilter::class)]
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

    /** El precio ya formateado con su moneda (`Q 100.00`), para los listados de la API. */
    #[ApiProperty(writable: false)]
    public function getImporte(): ?string
    {
        $precio = $this->getPrecio();
        if ($precio === null) {
            return null;
        }
        $moneda = $precio->getCurrency()->getCode();

        return sprintf("%s %s", $moneda === "GTQ" ? "Q" : $moneda, number_format((int) $precio->getAmount() / 100, 2));
    }

    /** Precio en unidades de su moneda (`125.5`) para el formulario; se guarda en centavos. */
    #[ApiProperty]
    #[Assert\NotNull(message: "El precio es obligatorio.")]
    #[Assert\PositiveOrZero]
    public function getPrecioMonto(): ?float
    {
        $precio = $this->getPrecio();

        return $precio === null ? null : (int) $precio->getAmount() / 100;
    }

    /** Conserva la moneda; una tarifa nueva, en quetzales. */
    public function setPrecioMonto(float $monto): static
    {
        $moneda = $this->getPrecio()?->getCurrency() ?? new Currency("GTQ");

        return $this->setPrecio(new Money((int) round($monto * 100), $moneda));
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
