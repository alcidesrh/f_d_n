<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Base\Traits\AccesoPorUsuarioTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Caja de la pantalla de administración (`/administracion`): título, ícono,
 * color y sus enlaces en orden. `activa` en falso la oculta (solo se ve al
 * configurar); quién la ve, por roles y usuarios (`AccesoPorUsuarioTrait`).
 * Solo se usa por `App\Administracion` y `/api/administracion`; no se publica
 * en GraphQL.
 */
#[ORM\Entity]
class SeccionAdministracion
{
    use AccesoPorUsuarioTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $titulo;

    /** Nombre de Material Symbols (`directions-bus-outline`). */
    #[ORM\Column(length: 80)]
    private string $icono;

    /** Rampa de color del frontend (`blue`, `emerald`…). */
    #[ORM\Column(length: 20)]
    private string $color;

    #[ORM\Column]
    private int $posicion = 0;

    #[ORM\Column]
    private bool $activa = true;

    /** @var Collection<int, EnlaceAdministracion> */
    #[ORM\OneToMany(targetEntity: EnlaceAdministracion::class, mappedBy: "seccion", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["posicion" => "ASC", "id" => "ASC"])]
    private Collection $enlaces;

    public function __construct(string $titulo, string $icono, string $color)
    {
        $this->titulo = $titulo;
        $this->icono = $icono;
        $this->color = $color;
        $this->enlaces = new ArrayCollection();
        $this->roles = new ArrayCollection();
        $this->usuarios = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function getIcono(): string
    {
        return $this->icono;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function describir(string $titulo, string $icono, string $color): static
    {
        $this->titulo = $titulo;
        $this->icono = $icono;
        $this->color = $color;

        return $this;
    }

    public function getPosicion(): int
    {
        return $this->posicion;
    }

    public function setPosicion(int $posicion): static
    {
        $this->posicion = $posicion;

        return $this;
    }

    public function isActiva(): bool
    {
        return $this->activa;
    }

    public function setActiva(bool $activa): static
    {
        $this->activa = $activa;

        return $this;
    }

    /** @return Collection<int, EnlaceAdministracion> */
    public function getEnlaces(): Collection
    {
        return $this->enlaces;
    }
}
