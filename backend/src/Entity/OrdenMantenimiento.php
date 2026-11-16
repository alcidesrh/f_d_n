<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un trabajo de taller hecho a un bus (ADR-065): qué día, con cuántos
 * kilómetros y cuánto costó. Con `plan` es el servicio de ese plan y desde
 * ella se cuenta el siguiente; sin plan es una reparación suelta, que solo
 * queda en el historial y en el costo del bus.
 */
#[ORM\Entity]
#[ORM\Index(name: "idx_orden_mantenimiento_bus_plan", columns: ["bus_id", "plan_id", "fecha"])]
#[ORM\HasLifecycleCallbacks]
#[ApiResourcePaginationPage]
#[Recurso(nombre: "Órdenes de mantenimiento", area: "Flota", empresa: "bus.empresa")]
class OrdenMantenimiento extends Base
{
    #[Assert\NotNull(message: "Elija el bus.")]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Bus $bus = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?PlanMantenimiento $plan = null;

    /** Día en que se hizo el trabajo. */
    #[Assert\NotNull(message: "La fecha es obligatoria.")]
    #[ORM\Column(type: "date")]
    private ?\DateTime $fecha = null;

    /** Lo que marcaba el odómetro del bus; sin dato, el que tiene el bus al registrarla. */
    #[Assert\PositiveOrZero]
    #[ORM\Column(nullable: true)]
    private ?int $odometroKm = null;

    /** En quetzales. */
    #[Assert\Type("numeric", message: "El costo debe ser un número.")]
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: "decimal", precision: 12, scale: 2, options: ["default" => "0.00"])]
    private string $costo = "0.00";

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $taller = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $descripcion = null;

    #[ORM\PrePersist]
    public function completarOdometro(): void
    {
        $this->odometroKm ??= $this->bus?->getOdometroKm();
    }

    #[Override]
    public function getLabel(): string
    {
        return trim(sprintf("%s · %s %s", $this->bus?->getCodigo() ?? "?", $this->plan?->getNombre() ?? "Reparación", $this->fecha?->format("d/m/Y") ?? ""));
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

    public function getPlan(): ?PlanMantenimiento
    {
        return $this->plan;
    }

    public function setPlan(?PlanMantenimiento $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getFecha(): ?\DateTime
    {
        return $this->fecha;
    }

    public function setFecha(?\DateTime $fecha): static
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getOdometroKm(): ?int
    {
        return $this->odometroKm;
    }

    public function setOdometroKm(?int $odometroKm): static
    {
        $this->odometroKm = $odometroKm;

        return $this;
    }

    public function getCosto(): string
    {
        return $this->costo;
    }

    public function setCosto(string $costo): static
    {
        $this->costo = is_numeric($costo) ? number_format((float) $costo, 2, ".", "") : $costo;

        return $this;
    }

    public function getTaller(): ?string
    {
        return $this->taller;
    }

    public function setTaller(?string $taller): static
    {
        $this->taller = $taller;

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
