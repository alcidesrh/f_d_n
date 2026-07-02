<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Repository\ApiTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResourceNoPagination]
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
class ApiToken extends Base {
    use TimestampableEntityTrait;

    private const PERSONAL_ACCESS_TOKEN_PREFIX = 'fdn_';

    public const SCOPE_USER_EDIT = 'ROLE_USER_EDIT';
    public const SCOPE_TREASURE_CREATE = 'ROLE_TREASURE_CREATE';
    public const SCOPE_TREASURE_EDIT = 'ROLE_TREASURE_EDIT';

    public const SCOPES = [
        self::SCOPE_USER_EDIT => 'Edit User',
        self::SCOPE_TREASURE_CREATE => 'Create Treasures',
        self::SCOPE_TREASURE_EDIT => 'Edit Treasures',
    ];

    #[ORM\ManyToOne(inversedBy: 'apiTokens')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'cascade')]
    private ?Usuario $usuario = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expira = null;

    #[ORM\Column(length: 68)]
    private string $token;

    // #[ORM\Column]
    // private array $scopes = [];

    #[ORM\Column(nullable: true)]
    private ?bool $activo = null;

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

    public function getExpira(): ?\DateTimeImmutable {
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

    // public function getScopes(): array {
    //     return $this->scopes;
    // }

    // public function setScopes(array $scopes): self {
    //     $this->scopes = $scopes;

    //     return $this;
    // }

    public function isValid(): ?bool {
        return $this->activo; //($this->expira === null || $this->expira > new \DateTimeInterface()) && $this->activo;
    }

    public function isActivo(): ?bool {
        return $this->activo;
    }

    public function setActivo(?bool $activo): static {
        $this->activo = $activo;

        return $this;
    }
    public function __toString(): string {
        return $this->token;
    }
}
