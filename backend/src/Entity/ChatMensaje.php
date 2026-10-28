<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mensaje de un canal: texto, adjuntos y/o archivos. Un adjunto es una
 * referencia `{tipo, id}` a un registro del sistema (boleto, salida, bus…),
 * no una copia: quien lo lee ve el estado actual, según sus permisos. Sin
 * autor es un aviso del sistema. Puede responder a otro mensaje del canal.
 *
 * No se modifica: editar es escribir otro que responde al original
 * (`editaA`), y eliminar lo vacía y deja la marca (`eliminadoEn`) para que
 * las respuestas y los no leídos sigan cuadrando.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["canal_id", "id"], name: "idx_chat_mensaje_canal")]
class ChatMensaje
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ChatCanal $canal;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $autor;

    #[ORM\Column(type: "text")]
    private string $texto;

    /** @var list<array{tipo: string, id: int}> */
    #[ORM\Column(type: "json", options: ["jsonb" => true])]
    private array $adjuntos;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?ChatMensaje $respuestaA = null;

    /** El mensaje que corrige (también es `respuestaA`): este se ve como "editado". */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?ChatMensaje $editaA = null;

    /** Reenviado desde otra conversación. */
    #[ORM\Column(options: ["default" => false])]
    private bool $reenviado = false;

    /** El grupo miembro por el que el autor estaba en el canal al escribir (se muestra junto a su nombre). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?ChatCanal $via = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $eliminadoEn = null;

    /** @var Collection<int, ChatArchivo> */
    #[ORM\OneToMany(mappedBy: "mensaje", targetEntity: ChatArchivo::class)]
    #[ORM\OrderBy(["id" => "ASC"])]
    private Collection $archivos;

    /**
     * @param list<array{tipo: string, id: int}> $adjuntos
     * @param list<ChatArchivo>                  $archivos
     */
    public function __construct(ChatCanal $canal, ?Usuario $autor, string $texto, array $adjuntos = [], array $archivos = [], ?ChatMensaje $respuestaA = null)
    {
        $this->canal = $canal;
        $this->autor = $autor;
        $this->texto = $texto;
        $this->adjuntos = $adjuntos;
        $this->respuestaA = $respuestaA;
        $this->creadoEn = new \DateTimeImmutable();
        $this->archivos = new ArrayCollection();
        foreach ($archivos as $a) {
            $a->adjuntarA($this);
            $this->archivos->add($a);
        }
    }

    public function comoEdicionDe(ChatMensaje $original): void
    {
        $this->editaA = $original;
        $this->respuestaA = $original;
    }

    public function comoReenvio(): void
    {
        $this->reenviado = true;
    }

    public function desde(?ChatCanal $via): void
    {
        $this->via = $via;
    }

    /**
     * Lo vacía (texto y registros); los archivos los quita quien llama
     * (`Archivos::borrar`), porque viven en disco.
     */
    public function eliminar(): void
    {
        $this->texto = "";
        $this->adjuntos = [];
        $this->eliminadoEn = new \DateTimeImmutable();
    }

    public function estaEliminado(): bool
    {
        return $this->eliminadoEn !== null;
    }

    public function getEditaA(): ?ChatMensaje
    {
        return $this->editaA;
    }

    public function esReenvio(): bool
    {
        return $this->reenviado;
    }

    public function getVia(): ?ChatCanal
    {
        return $this->via;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCanal(): ChatCanal
    {
        return $this->canal;
    }

    public function getAutor(): ?Usuario
    {
        return $this->autor;
    }

    public function getTexto(): string
    {
        return $this->texto;
    }

    /** @return list<array{tipo: string, id: int}> */
    public function getAdjuntos(): array
    {
        return $this->adjuntos;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }

    public function getRespuestaA(): ?ChatMensaje
    {
        return $this->respuestaA;
    }

    /** @return Collection<int, ChatArchivo> */
    public function getArchivos(): Collection
    {
        return $this->archivos;
    }
}
