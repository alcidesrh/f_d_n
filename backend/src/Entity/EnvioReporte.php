<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Constancia de que un reporte programado del día ya salió para un usuario
 * (ADR-044): uno por usuario, reporte y día. Hace idempotente el envío: un
 * reintento, una corrida perdida que se recupera o una ejecución a mano no
 * lo repiten. La escribe `EntregaReportes` por DBAL antes de enviar y la
 * borra si el envío falla, para que el reintento lo vuelva a intentar.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\UniqueConstraint(name: "uq_envio_reporte", columns: ["usuario_id", "reporte", "dia"])]
class EnvioReporte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $usuario;

    #[ORM\Column(length: 40)]
    private string $reporte;

    #[ORM\Column(type: "date_immutable")]
    private \DateTimeImmutable $dia;

    #[ORM\Column]
    private \DateTimeImmutable $enviadoEn;

    /** Qué salió: `{chat, correo, archivos}`. */
    #[ORM\Column(type: "json", nullable: true)]
    private ?array $detalle = null;

    private function __construct() {}

    public function getId(): ?int
    {
        return $this->id;
    }
}
