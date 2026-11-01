<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * La dirección de la empresa de un usuario: `{local}@{CORREO_DOMINIO}`. Se
 * guarda solo la parte local, así cambiar el dominio no toca los datos. Se
 * crea sola la primera vez que el usuario abre el correo o le llega uno
 * (`App\Correo\Cuentas`).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uniq_correo_cuenta_local", columns: ["local"])]
#[ORM\UniqueConstraint(name: "uniq_correo_cuenta_usuario", columns: ["usuario_id"])]
class CorreoCuenta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $usuario;

    /** Parte local de la dirección, en minúsculas. */
    #[ORM\Column(length: 64)]
    private string $local;

    #[ORM\Column]
    private \DateTimeImmutable $creadaEn;

    public function __construct(Usuario $usuario, string $local)
    {
        $this->usuario = $usuario;
        $this->local = strtolower($local);
        $this->creadaEn = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function getLocal(): string
    {
        return $this->local;
    }

    public function getCreadaEn(): \DateTimeImmutable
    {
        return $this->creadaEn;
    }
}
