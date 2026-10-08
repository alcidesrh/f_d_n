<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Archivo subido al chat (foto, PDF…). Se sube antes de enviar el mensaje y
 * queda suelto (`mensaje` nulo) hasta que un mensaje del mismo autor lo usa;
 * los sueltos viejos se purgan (`app:chat:purgar-archivos`). El contenido
 * vive en disco (`App\Chat\Archivos`), aquí solo los metadatos.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["mensaje_id"], name: "idx_chat_archivo_mensaje")]
class ChatArchivo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "archivos")]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?ChatMensaje $mensaje = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Usuario $autor;

    #[ORM\Column(length: 160)]
    private string $nombre;

    #[ORM\Column(length: 100)]
    private string $tipo;

    /** Bytes. */
    #[ORM\Column]
    private int $tamano;

    #[ORM\Column(nullable: true)]
    private ?int $ancho = null;

    #[ORM\Column(nullable: true)]
    private ?int $alto = null;

    /** Relativa al directorio de archivos del chat. */
    #[ORM\Column(length: 120)]
    private string $ruta;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    public function __construct(Usuario $autor, string $nombre, string $tipo, int $tamano, string $ruta, ?int $ancho = null, ?int $alto = null)
    {
        $this->autor = $autor;
        $this->nombre = $nombre;
        $this->tipo = $tipo;
        $this->tamano = $tamano;
        $this->ruta = $ruta;
        $this->ancho = $ancho;
        $this->alto = $alto;
        $this->creadoEn = new \DateTimeImmutable();
    }

    public function adjuntarA(ChatMensaje $mensaje): void
    {
        $this->mensaje = $mensaje;
    }

    public function esImagen(): bool
    {
        return str_starts_with($this->tipo, "image/");
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMensaje(): ?ChatMensaje
    {
        return $this->mensaje;
    }

    public function getAutor(): Usuario
    {
        return $this->autor;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function getTamano(): int
    {
        return $this->tamano;
    }

    public function getAncho(): ?int
    {
        return $this->ancho;
    }

    public function getAlto(): ?int
    {
        return $this->alto;
    }

    public function getRuta(): string
    {
        return $this->ruta;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }
}
