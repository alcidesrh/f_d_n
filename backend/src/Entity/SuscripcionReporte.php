<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un usuario quiere recibir un reporte programado (ADR-044,
 * `App\Reporte\Diario\ReporteProgramado`) por el chat (sus avisos del
 * sistema), por correo o por ambos. Se elige en Mi cuenta. Suscribirse no da
 * permisos: cada envío exige la capacidad del reporte y se recorta a su
 * alcance de ese momento; sin ella, no le llega.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_suscripcion_reporte", columns: ["usuario_id", "reporte"])]
class SuscripcionReporte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $usuario;

    /** `ReporteProgramado` */
    #[ORM\Column(length: 40)]
    private string $reporte;

    #[ORM\Column(options: ["default" => true])]
    private bool $porChat = true;

    #[ORM\Column(options: ["default" => false])]
    private bool $porCorreo = false;

    #[ORM\Column]
    private \DateTimeImmutable $actualizadaEn;

    public function __construct(Usuario $usuario, string $reporte)
    {
        $this->usuario = $usuario;
        $this->reporte = $reporte;
        $this->actualizadaEn = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function getReporte(): string
    {
        return $this->reporte;
    }

    public function isPorChat(): bool
    {
        return $this->porChat;
    }

    public function isPorCorreo(): bool
    {
        return $this->porCorreo;
    }

    public function cambiar(bool $porChat, bool $porCorreo): void
    {
        $this->porChat = $porChat;
        $this->porCorreo = $porCorreo;
        $this->actualizadaEn = new \DateTimeImmutable();
    }

    public function getActualizadaEn(): \DateTimeImmutable
    {
        return $this->actualizadaEn;
    }
}
