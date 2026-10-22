<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entidad externa que vende boletos por comisión (ADR-021). Sus usuarios
 * (`Usuario.agencia`) usan la misma interfaz de venta que la taquilla, con
 * dos diferencias: cada venta descuenta su total del `saldo` —que nunca
 * puede quedar negativo— y no lleva factura electrónica.
 *
 * El saldo solo cambia por `App\Venta\Agencia\SaldoAgencia` (depósitos,
 * ventas, ajustes), que deja cada cambio en `AgenciaMovimiento`. En el
 * legado era una `estacion` con `tipoEstacion_id = 4`.
 *
 * `empresas` son las que vende (sin ninguna, todas): son también el alcance
 * de sus usuarios (ADR-039).
 */
#[ORM\Entity]
#[ApiResourcePaginationPage]
#[Recurso(nombre: "Agencias", area: "Agencias", lecturaLibre: true)]
class Agencia extends Base
{
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $nit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $email = null;

    /**
     * Empresas cuyas salidas vende; sin ninguna, las de cualquier empresa.
     *
     * @var Collection<int, Empresa>
     */
    #[ORM\ManyToMany(targetEntity: Empresa::class)]
    #[ORM\JoinTable(name: "agencia_empresa")]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    #[ORM\InverseJoinColumn(onDelete: "CASCADE")]
    private Collection $empresas;

    public function __construct()
    {
        $this->empresas = new ArrayCollection();
    }

    /** Saldo disponible en centavos de `moneda`. Solo lo cambia `SaldoAgencia`. */
    #[ApiProperty(writable: false)]
    #[ORM\Column(type: "bigint", options: ["default" => 0])]
    private int|string $saldo = 0;

    #[ORM\Column(length: 3, options: ["default" => "GTQ"])]
    private string $moneda = "GTQ";

    /** Porcentaje que se bonifica sobre cada depósito (0–100). */
    #[ORM\Column(type: "decimal", precision: 5, scale: 2, nullable: true)]
    private ?string $porcentajeBonificacion = null;

    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $legacyId = null;

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getNit(): ?string
    {
        return $this->nit;
    }

    public function setNit(?string $nit): static
    {
        $this->nit = $nit;

        return $this;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): static
    {
        $this->direccion = $direccion;

        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /** @return Collection<int, Empresa> */
    public function getEmpresas(): Collection
    {
        return $this->empresas;
    }

    public function addEmpresa(Empresa $empresa): static
    {
        if (!$this->empresas->contains($empresa)) {
            $this->empresas->add($empresa);
        }

        return $this;
    }

    public function removeEmpresa(Empresa $empresa): static
    {
        $this->empresas->removeElement($empresa);

        return $this;
    }

    /** @return list<int>|null ids de las empresas que vende; null = todas */
    public function empresaIds(): ?array
    {
        $ids = array_values(array_map(static fn(Empresa $e) => (int) $e->getId(), $this->empresas->toArray()));

        return $ids === [] ? null : $ids;
    }

    /** Vende salidas de esa empresa. */
    public function vende(?Empresa $empresa): bool
    {
        $ids = $this->empresaIds();

        return $ids === null || ($empresa !== null && in_array((int) $empresa->getId(), $ids, true));
    }

    public function getSaldo(): int
    {
        return (int) $this->saldo;
    }

    /** Solo para `SaldoAgencia`: el saldo se mueve con un `AgenciaMovimiento`. */
    public function aplicarMovimiento(int $centavos): void
    {
        $nuevo = $this->getSaldo() + $centavos;
        if ($nuevo < 0) {
            throw new \DomainException("El saldo de la agencia no puede quedar negativo.");
        }
        $this->saldo = $nuevo;
    }

    public function getMoneda(): string
    {
        return $this->moneda;
    }

    public function setMoneda(string $moneda): static
    {
        $this->moneda = strtoupper($moneda);

        return $this;
    }

    public function getPorcentajeBonificacion(): ?string
    {
        return $this->porcentajeBonificacion;
    }

    public function setPorcentajeBonificacion(?string $porcentajeBonificacion): static
    {
        $this->porcentajeBonificacion = $porcentajeBonificacion;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

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
}
