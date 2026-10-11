<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Camino que recorre un bus: sus paradas en orden, de la primera (origen) a
 * la última (destino). No depende de la empresa ni de la fecha. Cada par de
 * paradas en orden es un trayecto que se puede vender en ella (ADR-030).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_ruta_legacy_id", columns: ["legacy_id"])]
#[ApiResourcePaginationPage]
class Ruta extends Base
{
    #[ORM\Column(length: 255)]
    private string $nombre = "";

    #[ORM\Column(nullable: true)]
    private ?int $kilometros = null;

    #[ORM\Column]
    private bool $activo = true;

    /** Código de la ruta del legado. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $legacyId = null;

    /**
     * Se escriben con el editor de paradas, no por el CRUD.
     *
     * @var Collection<int, Parada>
     */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\OneToMany(targetEntity: Parada::class, mappedBy: "ruta", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["orden" => "ASC"])]
    private Collection $paradas;

    public function __construct()
    {
        $this->paradas = new ArrayCollection();
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getKilometros(): ?int
    {
        return $this->kilometros;
    }

    public function setKilometros(?int $kilometros): static
    {
        $this->kilometros = $kilometros;

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

    /** @return Collection<int, Parada> en orden */
    public function getParadas(): Collection
    {
        return $this->paradas;
    }

    public function getOrigen(): ?Enclave
    {
        $primera = $this->paradas->first();

        return $primera instanceof Parada ? $primera->getEnclave() : null;
    }

    /** `Origen → Destino`. */
    public function getPuntas(): string
    {
        return sprintf("%s → %s", $this->getOrigen()?->getNombre() ?? "?", $this->getDestino()?->getNombre() ?? "?");
    }

    /**
     * Minutos hasta la última parada; con varias clases de bus, el mayor
     * (para no chocar dos salidas del mismo bus). Null si no se conoce.
     */
    public function getDuracionMinutos(): ?int
    {
        $ultima = $this->paradas->last();
        if (!$ultima instanceof Parada) {
            return null;
        }
        $minutos = array_filter([$ultima->getMinutos(), ...array_map(static fn(ParadaTiempo $t) => $t->getMinutos(), $ultima->getTiempos()->toArray())], static fn(?int $m) => $m !== null);

        return $minutos === [] ? null : max($minutos);
    }

    public function getDestino(): ?Enclave
    {
        $ultima = $this->paradas->last();

        return $ultima instanceof Parada ? $ultima->getEnclave() : null;
    }
}
