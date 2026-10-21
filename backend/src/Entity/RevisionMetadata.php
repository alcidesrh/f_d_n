<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Revisión de la metadata que el frontend guarda en el navegador (schema
 * GraphQL y configuración de entidades, ADR-036): qué quedó publicado
 * (`estado`), qué cambió, quién y por qué. Solo se agrega; el id es el número
 * de la revisión. La escribe y la lee `App\Metadata\RegistroRevisiones` (DBAL);
 * no se publica en GraphQL.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: "revision_metadata")]
class RevisionMetadata
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Huellas y revisiones vigentes tras esta (`App\Metadata\Estado::toArray()`).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: "json")]
    private array $estado = [];

    /**
     * Qué cambió respecto de la anterior (`App\Metadata\Cambios::toArray()`).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: "json")]
    private array $cambios = [];

    /** `App\Metadata\Origen`. */
    #[ORM\Column(length: 20)]
    private string $origen;

    /** Cada navegador ofrece «Recargar» con la nota. */
    #[ORM\Column]
    private bool $aviso = false;

    /** Cada navegador vuelve a pedir todo (schema, configuración y listas guardadas). */
    #[ORM\Column]
    private bool $completa = false;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $nota = null;

    /** Quien la publicó a mano; null si fue automática o del despliegue. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $usuario = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $usuarioNombre = null;

    #[ORM\Column]
    private \DateTimeImmutable $creadaEn;

    private function __construct() {}

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function getEstado(): array
    {
        return $this->estado;
    }

    /** @return array<string, mixed> */
    public function getCambios(): array
    {
        return $this->cambios;
    }

    public function getOrigen(): string
    {
        return $this->origen;
    }

    public function isAviso(): bool
    {
        return $this->aviso;
    }

    public function isCompleta(): bool
    {
        return $this->completa;
    }

    public function getNota(): ?string
    {
        return $this->nota;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function getUsuarioNombre(): ?string
    {
        return $this->usuarioNombre;
    }

    public function getCreadaEn(): \DateTimeImmutable
    {
        return $this->creadaEn;
    }
}
