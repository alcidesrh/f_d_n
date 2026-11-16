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
 * Un servicio que se le hace a cada bus cada tanto (ADR-065): cambio de
 * aceite cada 10 000 km, revisión de frenos cada 180 días. Vence por
 * kilómetros, por días o por lo que llegue primero; cuenta desde la última
 * `OrdenMantenimiento` del bus con este plan. Vale para toda la flota.
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
#[Recurso(nombre: "Planes de mantenimiento", area: "Flota", lecturaLibre: true)]
class PlanMantenimiento extends Base
{
    #[Assert\NotBlank]
    #[ORM\Column(length: 80, unique: true)]
    private string $nombre = "";

    /** Cada cuántos kilómetros; null si solo vence por fecha. */
    #[Assert\Positive]
    #[ORM\Column(nullable: true)]
    private ?int $cadaKm = null;

    /** Cada cuántos días; null si solo vence por kilómetros. */
    #[Assert\Positive]
    #[ORM\Column(nullable: true)]
    private ?int $cadaDias = null;

    /** Uno inactivo no vence ni avisa; sus órdenes se conservan. */
    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $descripcion = null;

    #[Assert\Callback]
    public function validarIntervalo(ExecutionContextInterface $contexto): void
    {
        if ($this->cadaKm === null && $this->cadaDias === null) {
            $contexto->buildViolation("Indique cada cuántos kilómetros o cada cuántos días se hace.")->atPath("cadaKm")->addViolation();
        }
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = trim($nombre);

        return $this;
    }

    public function getCadaKm(): ?int
    {
        return $this->cadaKm;
    }

    public function setCadaKm(?int $cadaKm): static
    {
        $this->cadaKm = $cadaKm;

        return $this;
    }

    public function getCadaDias(): ?int
    {
        return $this->cadaDias;
    }

    public function setCadaDias(?int $cadaDias): static
    {
        $this->cadaDias = $cadaDias;

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

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

        return $this;
    }
}
