<?php

namespace App\Entity;

use App\Repository\EmpresaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;


#[ORM\Entity(repositoryClass: EmpresaRepository::class)]
class Empresa {

    public const rosita = 'transportes-rosita-sociedad-anonima';
    public const mitocha = 'autobuses-maya-de-oro-sociedad-anonima';
    public const pionera = 'transportes-fuente-del-norte-la-pionera-sociedad-anonima';

    public const nit_emisor = 69073031;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre_comercial = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $nit = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $empresa_id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $alias = null;

    #[ORM\Column(nullable: true)]
    private ?int $sat_id = null;

    #[Gedmo\Slug(fields: ['nombre'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(nullable: true)]
    private ?bool $activa = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self {
        $this->nombre = $nombre;

        return $this;
    }

    public function getNombreComercial(): ?string {
        return $this->nombre_comercial;
    }

    public function setNombreComercial(?string $nombre_comercial): self {
        $this->nombre_comercial = $nombre_comercial;

        return $this;
    }

    public function getDireccion(): ?string {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): self {
        $this->direccion = $direccion;

        return $this;
    }

    public function getNit(): ?string {
        return $this->nit;
    }

    public function setNit(?string $nit): self {
        $this->nit = $nit;

        return $this;
    }

    public function getEmpresaId(): ?string {
        return $this->empresa_id;
    }

    public function setEmpresaId(?string $empresa_id): self {
        $this->empresa_id = $empresa_id;

        return $this;
    }

    public function getAlias(): ?string {
        return $this->alias;
    }

    public function setAlias(?string $alias): self {
        $this->alias = $alias;

        return $this;
    }

    public function __toString() {
        return $this->alias;;
    }

    public function getSatId(): ?int {
        return $this->sat_id;
    }

    public function setSatId(?int $sat_id): self {
        $this->sat_id = $sat_id;

        return $this;
    }

    public function getSlug(): ?string {
        return $this->slug;
    }

    public function setSlug(string $slug): self {
        $this->slug = $slug;

        return $this;
    }

    public function isActiva(): ?bool
    {
        return $this->activa;
    }

    public function setActiva(?bool $activa): static
    {
        $this->activa = $activa;

        return $this;
    }
}
