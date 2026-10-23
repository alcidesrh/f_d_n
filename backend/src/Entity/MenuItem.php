<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Ítem navegable: texto, ícono y destino. Se crea de antemano y luego se
 * coloca en uno o varios `Menu` (cada menú decide su jerarquía y orden en su
 * propia `Taxonomy`, por eso `position` no vive aquí).
 *
 * El destino es una de tres cosas: una `route` (ruta del router), un `path`
 * escrito a mano (enlace) o ninguna, y entonces es un grupo: solo agrupa a
 * sus hijos en el menú, no navega.
 *
 * El texto es `nombre`; `label` es derivado de solo lectura, como en todas
 * las entidades.
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
#[Recurso(nombre: "Ítems de menú", area: "Configuración", lecturaLibre: true, editar: "menu.editar", eliminar: "menu.editar")]
class MenuItem extends Base
{
    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Icon $icon = null;

    /**
     * Si la ruta desaparece del router (sincronización), el ítem se borra con ella.
     * Nulo en un grupo.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?VueRoute $route = null;

    /**
     * Enlace escrito a mano (p. ej. `/salidas/chequeo`): para destinos que no
     * están en el router o que se quieren fijar sin depender de él. Excluyente
     * con `route`. La pantalla exige su capacidad igual que cualquier otra.
     */
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '#^/\S*$#', message: 'El enlace debe empezar con «/» y no tener espacios.')]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $path = null;

    #[Assert\Callback]
    public function validarDestino(ExecutionContextInterface $context): void
    {
        if ($this->route !== null && $this->path !== null) {
            $context->buildViolation('Un ítem navega a una ruta o a un enlace, no a los dos.')
                ->atPath('path')
                ->addViolation();
        }
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

    public function getIcon(): ?Icon
    {
        return $this->icon;
    }

    public function setIcon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getRoute(): ?VueRoute
    {
        return $this->route;
    }

    /** Nulo (con `path` nulo también) convierte el ítem en un grupo. */
    public function setRoute(?VueRoute $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    /** Excluyente con `route`: la validación lo exige al guardar. */
    public function setPath(?string $path): static
    {
        $this->path = $path;

        return $this;
    }
}
