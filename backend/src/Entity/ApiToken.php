<?php

namespace App\Entity;

use App\Acceso\Recurso;
use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Repository\ApiTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResourceNoPagination]
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[Recurso(nombre: "Sesiones (tokens)", area: "Accesos", oculto: true)]
class ApiToken extends Base {
    use TimestampableEntityTrait;

    private const PERSONAL_ACCESS_TOKEN_PREFIX = 'fdn_';

    #[ORM\ManyToOne(inversedBy: 'apiTokens')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'cascade')]
    private ?Usuario $usuario = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expira = null;

    /** Secreto del Bearer: nunca se expone por la API (solo lo devuelve el login). Único: se busca en cada petición. */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(length: 68, unique: true)]
    private string $token;

    #[ORM\Column(nullable: true)]
    private ?bool $activo = null;

    /**
     * Token de suplantación: el administrador del sistema que lo emitió para
     * entrar como `usuario` (`App\Acceso\Suplantacion\Suplantador`). Nunca lo
     * reutiliza el login normal del usuario.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Usuario $suplantadoPor = null;

    /** Por qué se suplanta (si la configuración lo exige). */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motivo = null;

    public function __construct(string $tokenType = self::PERSONAL_ACCESS_TOKEN_PREFIX) {
        $this->token = $tokenType . bin2hex(random_bytes(32));
    }


    public function getUsuario(): ?Usuario {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): self {
        $this->usuario = $usuario;

        return $this;
    }

    public function getExpira(): ?\DateTimeInterface {
        return $this->expira;
    }

    public function setExpira(?\DateTimeInterface $expira): self {
        $this->expira = $expira;

        return $this;
    }

    public function getToken(): ?string {
        return $this->token;
    }

    public function setToken(string $token): self {
        $this->token = $token;

        return $this;
    }

    /** Activo y sin vencer. */
    public function isValid(): bool {
        return (bool) $this->activo && ($this->expira === null || $this->expira > new \DateTimeImmutable());
    }

    public function isActivo(): ?bool {
        return $this->activo;
    }

    public function setActivo(?bool $activo): static {
        $this->activo = $activo;

        return $this;
    }
    public function getSuplantadoPor(): ?Usuario {
        return $this->suplantadoPor;
    }

    public function esSuplantacion(): bool {
        return $this->suplantadoPor !== null;
    }

    public function getMotivo(): ?string {
        return $this->motivo;
    }

    /** Lo marca como token de suplantación emitido por `$actor`. */
    public function suplantacion(Usuario $actor, ?string $motivo): self {
        $this->suplantadoPor = $actor;
        $this->motivo = $motivo;

        return $this;
    }

    public function __toString(): string {
        return $this->token;
    }
}
