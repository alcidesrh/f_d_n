<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Traza de un registro (`Salida`, `BoletoAsiento`): quién hizo qué y cuándo.
 * Solo se agrega, nunca se edita ni se borra, y no depende del registro por
 * clave foránea: sobrevive a que el registro se elimine. El usuario se guarda
 * también por nombre, por si luego se borra. Lo escribe
 * `App\Bitacora\BitacoraListener`; se lee por `GET /api/bitacora/{tipo}/{id}`.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: "bitacora")]
#[ORM\Index(columns: ["entidad", "registro_id", "fecha"], name: "idx_bitacora_registro")]
class Bitacora
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** `App\Bitacora\TipoRegistro`. */
    #[ORM\Column(length: 20)]
    private string $entidad;

    #[ORM\Column]
    private int $registroId;

    /** Valor de `OperacionSalida` / `OperacionBoleto`. */
    #[ORM\Column(length: 30)]
    private string $operacion;

    /** Quien la hizo; null si fue el sistema o un visitante de la página web. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $usuario = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $usuarioNombre = null;

    #[ORM\Column]
    private \DateTimeImmutable $fecha;

    /**
     * Datos de la operación (motivo, bus anterior…).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: "json", nullable: true)]
    private ?array $detalle = null;

    private function __construct() {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntidad(): string
    {
        return $this->entidad;
    }

    public function getRegistroId(): int
    {
        return $this->registroId;
    }

    public function getOperacion(): string
    {
        return $this->operacion;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function getUsuarioNombre(): ?string
    {
        return $this->usuarioNombre;
    }

    public function getFecha(): \DateTimeImmutable
    {
        return $this->fecha;
    }

    /** @return array<string, mixed>|null */
    public function getDetalle(): ?array
    {
        return $this->detalle;
    }
}
