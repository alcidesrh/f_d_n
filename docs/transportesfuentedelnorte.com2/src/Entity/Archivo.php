<?php

namespace App\Entity;

use App\Repository\ArchivoRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ArchivoRepository::class)]
class Archivo
{
    const IMAGEN = "imagen";
    const PDF = "pdf";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $extencion = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(nullable: true)]
    private ?bool $webP = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tipo = null;

    #[ORM\Column(nullable: true)]
    private ?bool $no_editar = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    #[Gedmo\Slug(fields: ['nombre'])]
    private ?string $slug = null;

    public function __construct()
    {
        $this->webP = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getExtencion(): ?string
    {
        return $this->extencion;
    }

    public function setExtencion(?string $extencion): self
    {
        $this->extencion = $extencion;

        return $this;
    }

    public function getPath(): ?string
    {
        return self::IMAGEN == $this->tipo ? 'images/' . $this->path : '';
    }

    public function getFileName(): ?string
    {
        return $this->path ?? '';
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function isWebP(): ?bool
    {
        return $this->webP;
    }

    public function setWebP(?bool $webP): self
    {
        $this->webP = $webP;

        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(?string $tipo): self
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function __toString()
    {
        return $this->path ?? '';
    }

    public function isNoEditar(): ?bool
    {
        return $this->no_editar;
    }

    public function setNoEditar(?bool $no_editar): self
    {
        $this->no_editar = $no_editar;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
