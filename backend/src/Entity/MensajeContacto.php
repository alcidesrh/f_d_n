<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Mensaje del formulario de contacto de la página web (ADR-023). Se guarda
 * siempre (el correo al buzón de contacto puede fallar) y se lee en el
 * dashboard de la página.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["creado_en"], name: "idx_mensaje_contacto_creado")]
class MensajeContacto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $nombre;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telefono;

    #[ORM\Column(type: "text")]
    private string $mensaje;

    #[ORM\Column(length: 5)]
    private string $idioma;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    #[ORM\Column(options: ["default" => false])]
    private bool $leido = false;

    public function __construct(string $nombre, string $email, ?string $telefono, string $mensaje, string $idioma, \DateTimeImmutable $creadoEn)
    {
        $this->nombre = $nombre;
        $this->email = $email;
        $this->telefono = $telefono;
        $this->mensaje = $mensaje;
        $this->idioma = $idioma;
        $this->creadoEn = $creadoEn;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function getMensaje(): string
    {
        return $this->mensaje;
    }

    public function getIdioma(): string
    {
        return $this->idioma;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }

    public function isLeido(): bool
    {
        return $this->leido;
    }

    public function marcarLeido(bool $leido = true): void
    {
        $this->leido = $leido;
    }
}
