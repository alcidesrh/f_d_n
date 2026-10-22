<?php

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Entity\Base\Constants\RolesTrait;
use App\Repository\RoleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rol (ADR-039): un puesto con nombre y el conjunto de capacidades del
 * catálogo que concede (`App\Acceso\Catalogo`), más sus límites. Plano: no
 * hereda de otros roles. Lo concede una `Asignacion` sobre un alcance; el rol
 * en sí no tiene alcance.
 *
 * - El de sistema (`sistema`) tiene todas las capacidades, también las que se
 *   agreguen al catálogo después; no se edita.
 * - `ROLE_USER` es el rol base: lo tiene todo usuario sin asignárselo y solo
 *   sirve para mostrar menús y cajas de administración a todos; no lleva
 *   capacidades.
 *
 * Se escribe por `/api/acceso/roles` (`App\Acceso\Administracion\AdministracionAccesos`),
 * que impide la escalada de privilegios; por la API genérica solo se lee.
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ApiResourceNoPagination]
#[Recurso(nombre: 'Roles', area: 'Accesos', lecturaLibre: true, soloLectura: true)]
class Role extends Base
{
    use RolesTrait;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcion = null;

    /** @var list<string> códigos del catálogo */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $capacidades = [];

    /** @var array<string, int> código de `App\Acceso\Limite` → valor */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $limites = [];

    #[ORM\Column(options: ['default' => false])]
    private bool $sistema = false;

    public function getId(): ?int
    {
        return $this->id ?? null;
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

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }

    /** @return list<string> */
    public function getCapacidades(): array
    {
        return $this->capacidades;
    }

    /** @param list<string> $capacidades */
    public function setCapacidades(array $capacidades): static
    {
        $capacidades = array_values(array_unique($capacidades));
        sort($capacidades);
        $this->capacidades = $capacidades;

        return $this;
    }

    /** @return array<string, int> */
    public function getLimites(): array
    {
        return $this->limites;
    }

    /** @param array<string, int> $limites */
    public function setLimites(array $limites): static
    {
        ksort($limites);
        $this->limites = $limites;

        return $this;
    }

    public function isSistema(): bool
    {
        return $this->sistema;
    }

    public function setSistema(bool $sistema): static
    {
        $this->sistema = $sistema;

        return $this;
    }

    /** El rol base (`ROLE_USER`): lo tiene todo usuario, sin capacidades. */
    public function esBase(): bool
    {
        return $this->nombre === self::user;
    }

    public function __toString(): string
    {
        return $this->getNombre() ?? '';
    }
}
