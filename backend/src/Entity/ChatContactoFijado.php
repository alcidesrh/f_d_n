<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un contacto que `usuario` fijó en el chat: aparece siempre primero en su
 * bandeja y en sus listas de personas. Es personal (nadie más lo ve). Si
 * cualquiera de los dos usuarios se borra, el fijado se borra con él.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uniq_chat_contacto_fijado", columns: ["usuario_id", "contacto_id"])]
class ChatContactoFijado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Quien fijó. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $usuario;

    /** La persona fijada. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $contacto;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    public function __construct(Usuario $usuario, Usuario $contacto)
    {
        $this->usuario = $usuario;
        $this->contacto = $contacto;
        $this->creadoEn = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function getContacto(): Usuario
    {
        return $this->contacto;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }
}
