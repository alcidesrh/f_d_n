<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Constancia de que una instancia de mensaje predeterminado ya salió por un
 * motivo (ADR-045): una por instancia y clave (`salida:123`, `dia:2026-10-29`,
 * `anomalias:2026-10-28`). Hace idempotente el envío, como `EnvioReporte`: un
 * reintento o una corrida recuperada no lo repiten. La escribe `Difusion` por
 * DBAL antes de enviar y la borra si el envío falla.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\UniqueConstraint(name: "uq_envio_mensaje_predeterminado", columns: ["mensaje_id", "clave"])]
class EnvioMensajePredeterminado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private MensajePredeterminado $mensaje;

    #[ORM\Column(length: 60)]
    private string $clave;

    #[ORM\Column]
    private \DateTimeImmutable $enviadoEn;

    /** Cuántos lo recibieron. */
    #[ORM\Column(options: ["default" => 0])]
    private int $destinatarios = 0;

    private function __construct() {}

    public function getId(): ?int
    {
        return $this->id;
    }
}
