<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Archivo de un correo. El contenido vive en disco (`App\Correo\Adjuntos`),
 * aquí los metadatos. Las copias de un mismo correo en varios buzones (o un
 * reenvío) comparten `ruta`: el archivo se borra con el último que lo usa.
 * `contentId` es el de las imágenes incrustadas en el HTML (`cid:`).
 */
#[ORM\Entity]
#[ORM\Index(columns: ["mensaje_id"], name: "idx_correo_adjunto_mensaje")]
#[ORM\Index(columns: ["ruta"], name: "idx_correo_adjunto_ruta")]
class CorreoAdjunto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "adjuntos")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private CorreoMensaje $mensaje;

    #[ORM\Column(length: 160)]
    private string $nombre;

    #[ORM\Column(length: 100)]
    private string $tipo;

    #[ORM\Column]
    private int $tamano;

    #[ORM\Column(length: 120)]
    private string $ruta;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contentId;

    /** Incrustado en el HTML (no se ofrece como descarga aparte). */
    #[ORM\Column(options: ["default" => false])]
    private bool $enLinea;

    public function __construct(CorreoMensaje $mensaje, string $nombre, string $tipo, int $tamano, string $ruta, ?string $contentId = null, bool $enLinea = false)
    {
        $this->mensaje = $mensaje;
        $this->nombre = mb_substr($nombre, 0, 160);
        $this->tipo = mb_substr($tipo, 0, 100);
        $this->tamano = $tamano;
        $this->ruta = $ruta;
        $this->contentId = $contentId !== null && $contentId !== "" ? mb_substr($contentId, 0, 255) : null;
        $this->enLinea = $enLinea && $this->contentId !== null;
        $mensaje->agregarAdjunto($this);
    }

    /** Otra fila para otro correo con el mismo archivo en disco. */
    public function copiarA(CorreoMensaje $mensaje): self
    {
        return new self($mensaje, $this->nombre, $this->tipo, $this->tamano, $this->ruta, $this->contentId, $this->enLinea);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMensaje(): CorreoMensaje
    {
        return $this->mensaje;
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

    public function getRuta(): string
    {
        return $this->ruta;
    }

    public function getContentId(): ?string
    {
        return $this->contentId;
    }

    public function isEnLinea(): bool
    {
        return $this->enLinea;
    }

    public function esImagen(): bool
    {
        return in_array($this->tipo, ["image/jpeg", "image/png", "image/gif", "image/webp"], true);
    }
}
