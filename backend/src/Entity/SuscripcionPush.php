<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un navegador (o la app instalada) que recibe las notificaciones del chat
 * con la aplicación cerrada (Web Push). El `endpoint` identifica al
 * navegador: si otro usuario se suscribe desde el mismo, pasa a ser suyo.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uniq_suscripcion_push_endpoint", columns: ["endpoint"])]
class SuscripcionPush
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $usuario;

    #[ORM\Column(length: 1000)]
    private string $endpoint;

    /** Clave pública del navegador (`p256dh`, base64url). */
    #[ORM\Column(length: 200)]
    private string $clave;

    /** Secreto de autenticación del navegador (`auth`, base64url). */
    #[ORM\Column(length: 100)]
    private string $secreto;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    public function __construct(Usuario $usuario, string $endpoint, string $clave, string $secreto)
    {
        $this->endpoint = $endpoint;
        $this->creadoEn = new \DateTimeImmutable();
        $this->renovar($usuario, $clave, $secreto);
    }

    public function renovar(Usuario $usuario, string $clave, string $secreto): void
    {
        $this->usuario = $usuario;
        $this->clave = $clave;
        $this->secreto = $secreto;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getClave(): string
    {
        return $this->clave;
    }

    public function getSecreto(): string
    {
        return $this->secreto;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }
}
