<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Usuario dentro de un canal, con hasta qué mensaje leyó. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uniq_chat_miembro", columns: ["canal_id", "usuario_id"])]
class ChatMiembro
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "miembros")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ChatCanal $canal;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $usuario;

    /** Id del último `ChatMensaje` leído (0 = ninguno). */
    #[ORM\Column]
    private int $ultimoLeido = 0;

    public function __construct(ChatCanal $canal, Usuario $usuario)
    {
        $this->canal = $canal;
        $this->usuario = $usuario;
    }

    /** Nunca retrocede: leer en otro dispositivo un mensaje viejo no "desmarca". */
    public function leyoHasta(int $mensajeId): void
    {
        $this->ultimoLeido = max($this->ultimoLeido, $mensajeId);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCanal(): ChatCanal
    {
        return $this->canal;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function getUltimoLeido(): int
    {
        return $this->ultimoLeido;
    }
}
