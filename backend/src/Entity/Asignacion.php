<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Asignación (ADR-039): un `Role` concedido a un `Usuario` sobre un alcance
 * —conjunto de empresas × conjunto de estaciones, `null` = todas, también
 * las que se den de alta después— y, si hace falta, por un tiempo (`desde`,
 * `hasta`, inclusive). Lo que puede un usuario es la unión de sus
 * asignaciones vigentes: no hay negaciones ni excepciones por usuario.
 *
 * A un usuario de agencia el alcance no se le elige: siempre es su agencia
 * con las empresas que esta vende (`App\Acceso\Autorizacion`).
 *
 * Solo se escribe por `/api/acceso` (sin GraphQL), que impide la escalada de
 * privilegios y lo anota en la bitácora del usuario.
 */
#[ORM\Entity]
#[ORM\Table(name: 'asignacion')]
#[ORM\Index(name: 'idx_asignacion_usuario', columns: ['usuario_id'])]
#[ORM\Index(name: 'idx_asignacion_rol', columns: ['rol_id'])]
class Asignacion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'asignaciones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Usuario $usuario;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Role $rol;

    /** @var list<int>|null ids de `Empresa`; null = todas */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $empresas = null;

    /** @var list<int>|null ids de `Estacion`; null = todas */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $estaciones = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $desde = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $hasta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motivo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Usuario $otorgadaPor = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creada;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $modificada;

    public function __construct(Usuario $usuario, Role $rol)
    {
        $this->usuario = $usuario;
        $this->rol = $rol;
        $this->creada = $this->modificada = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    public function getRol(): Role
    {
        return $this->rol;
    }

    public function setRol(Role $rol): static
    {
        $this->rol = $rol;

        return $this;
    }

    /** @return list<int>|null */
    public function getEmpresas(): ?array
    {
        return $this->empresas;
    }

    /** @param list<int>|null $empresas */
    public function setEmpresas(?array $empresas): static
    {
        $this->empresas = self::ids($empresas);

        return $this;
    }

    /** @return list<int>|null */
    public function getEstaciones(): ?array
    {
        return $this->estaciones;
    }

    /** @param list<int>|null $estaciones */
    public function setEstaciones(?array $estaciones): static
    {
        $this->estaciones = self::ids($estaciones);

        return $this;
    }

    public function getDesde(): ?\DateTimeImmutable
    {
        return $this->desde;
    }

    public function setDesde(?\DateTimeImmutable $desde): static
    {
        $this->desde = $desde;

        return $this;
    }

    public function getHasta(): ?\DateTimeImmutable
    {
        return $this->hasta;
    }

    public function setHasta(?\DateTimeImmutable $hasta): static
    {
        $this->hasta = $hasta;

        return $this;
    }

    public function getMotivo(): ?string
    {
        return $this->motivo;
    }

    public function setMotivo(?string $motivo): static
    {
        $this->motivo = $motivo !== null && trim($motivo) !== '' ? mb_substr(trim($motivo), 0, 255) : null;

        return $this;
    }

    public function getOtorgadaPor(): ?Usuario
    {
        return $this->otorgadaPor;
    }

    public function setOtorgadaPor(?Usuario $usuario): static
    {
        $this->otorgadaPor = $usuario;

        return $this;
    }

    public function getCreada(): \DateTimeImmutable
    {
        return $this->creada;
    }

    public function getModificada(): \DateTimeImmutable
    {
        return $this->modificada;
    }

    public function marcarModificada(): static
    {
        $this->modificada = new \DateTimeImmutable();

        return $this;
    }

    /** Rige ese día (las fechas son inclusive). */
    public function vigente(\DateTimeImmutable $hoy): bool
    {
        $dia = $hoy->format('Y-m-d');

        return ($this->desde === null || $this->desde->format('Y-m-d') <= $dia)
            && ($this->hasta === null || $this->hasta->format('Y-m-d') >= $dia);
    }

    /**
     * @param list<int|string>|null $ids
     *
     * @return list<int>|null
     */
    private static function ids(?array $ids): ?array
    {
        if ($ids === null) {
            return null;
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }
}
