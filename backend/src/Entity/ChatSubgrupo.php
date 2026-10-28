<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un grupo abierto que es miembro de otro grupo, como si fuera una persona:
 * sus miembros reciben y (si el grupo los deja) escriben en el otro. Ellos
 * quedan como `ChatMiembro` del otro con `via` = este grupo, así la bandeja,
 * los no leídos y el "visto" no cambian. Un solo nivel: un grupo miembro no
 * tiene grupos miembros, y un grupo con grupos miembros no es miembro de otro.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uniq_chat_subgrupo", columns: ["canal_id", "grupo_id"])]
class ChatSubgrupo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** El grupo que lo contiene. */
    #[ORM\ManyToOne(inversedBy: "subgrupos")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ChatCanal $canal;

    /** El grupo miembro. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ChatCanal $grupo;

    /** En un grupo dirigido: sus miembros pueden escribir. */
    #[ORM\Column(options: ["default" => false])]
    private bool $emisor = false;

    public function __construct(ChatCanal $canal, ChatCanal $grupo, bool $emisor = false)
    {
        $this->canal = $canal;
        $this->grupo = $grupo;
        $this->emisor = $emisor;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCanal(): ChatCanal
    {
        return $this->canal;
    }

    public function getGrupo(): ChatCanal
    {
        return $this->grupo;
    }

    public function esEmisor(): bool
    {
        return $this->emisor;
    }

    public function setEmisor(bool $emisor): void
    {
        $this->emisor = $emisor;
    }
}
