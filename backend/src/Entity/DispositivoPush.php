<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un teléfono con la app móvil (ADR-060) que recibe las notificaciones del
 * chat con la app cerrada, por Firebase Cloud Messaging. El `token` lo da
 * Firebase a esa instalación: si otro usuario entra en el mismo teléfono,
 * pasa a ser suyo. El navegador y la PWA usan `SuscripcionPush`.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uniq_dispositivo_push_token", columns: ["token"])]
class DispositivoPush
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $usuario;

    #[ORM\Column(length: 500)]
    private string $token;

    /** `android` o `ios`. */
    #[ORM\Column(length: 20)]
    private string $plataforma;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    public function __construct(Usuario $usuario, string $token, string $plataforma)
    {
        $this->token = $token;
        $this->creadoEn = new \DateTimeImmutable();
        $this->renovar($usuario, $plataforma);
    }

    public function renovar(Usuario $usuario, string $plataforma): void
    {
        $this->usuario = $usuario;
        $this->plataforma = $plataforma;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPlataforma(): string
    {
        return $this->plataforma;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }
}
