<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Usuario dentro de un canal, con hasta qué mensaje leyó. En un grupo puede
 * estar por sí mismo (`via` nulo) o por ser miembro de un grupo miembro
 * (`via` = ese grupo, `ChatSubgrupo`).
 */
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

    /** El grupo miembro por el que está aquí; nulo = miembro por sí mismo. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?ChatCanal $via = null;

    /** En un grupo dirigido: el dueño lo eligió para escribir (solo los miembros por sí mismos). */
    #[ORM\Column(options: ["default" => false])]
    private bool $emisor = false;

    public function __construct(ChatCanal $canal, Usuario $usuario, ?ChatCanal $via = null)
    {
        $this->canal = $canal;
        $this->usuario = $usuario;
        $this->via = $via;
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

    public function getVia(): ?ChatCanal
    {
        return $this->via;
    }

    public function esDirecto(): bool
    {
        return $this->via === null;
    }

    /** Pasa a estar por sí mismo (null) o por un grupo miembro; por un grupo no escribe por sí mismo. */
    public function setVia(?ChatCanal $via): void
    {
        $this->via = $via;
        if ($via !== null) {
            $this->emisor = false;
        }
    }

    public function esEmisor(): bool
    {
        return $this->emisor;
    }

    public function setEmisor(bool $emisor): void
    {
        $this->emisor = $emisor && $this->via === null;
    }
}
