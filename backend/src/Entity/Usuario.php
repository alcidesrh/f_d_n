<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Base\PersonaBase;
use App\Filter\ColumnaFilter;
use App\Filter\IdPartialSearchFilter;
use App\Repository\UsuarioRepository;
use App\Resolver\UserByUsernameResolver;
use App\Services\UsuarioPasswordHasher;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
// Se borran por `/api/usuarios/eliminacion` (decide qué hacer con lo que depende de ellos).
#[Recurso(nombre: "Usuarios", area: "Accesos", borrable: false)]
#[
    ApiResourcePaginationPage(
        graphQlOperations: [
            new Query(),
            new Mutation(
                name: "create",
                processor: UsuarioPasswordHasher::class,
                validationContext: ["groups" => ["Default", "usuario:crear"]],
            ),
            new Mutation(
                name: "update",
                processor: UsuarioPasswordHasher::class,
            ),
            new QueryCollection(
                paginationType: "page",
                filters: [ColumnaFilter::class],
                parameters: [
                    "id" => new QueryParameter(
                        filter: new OrFilter(new IdPartialSearchFilter()),
                        property: "id",
                    ),
                    "username" => new QueryParameter(
                        filter: new OrFilter(new PartialSearchFilter()),
                        property: "username",
                    ),
                    "nombre" => new QueryParameter(
                        filter: new OrFilter(new PartialSearchFilter()),
                        property: "nombre",
                    ),
                    "apellido" => new QueryParameter(
                        filter: new OrFilter(new PartialSearchFilter()),
                        property: "apellido",
                    ),
                    "nit" => new QueryParameter(
                        filter: new OrFilter(new PartialSearchFilter()),
                        property: "nit",
                    ),
                    "email" => new QueryParameter(
                        filter: new OrFilter(new PartialSearchFilter()),
                        property: "email",
                    ),
                ],
            ),
            new Query(
                name: "getByUsername",
                resolver: UserByUsernameResolver::class,
                args: ["username" => ["type" => "String"]],
            ),
        ],
    ),
]
#[ApiFilter(DateFilter::class, properties: ["createdAt", "updatedAt"])]
#[
    ApiFilter(
        SearchFilter::class,
        properties: [
            "localidad.id" => "exact",
            "status.id" => "exact",
        ],
    ),
]
#[
    ApiFilter(
        OrderFilter::class,
        properties: [
            "id",
            "nombre",
            "apellido",
            "username",
            "createdAt",
            "email",
        ],
        arguments: ["orderParameterName" => "order"],
    ),
]
class Usuario extends PersonaBase implements
    UserInterface,
    PasswordAuthenticatedUserInterface
{
    #[ORM\Column(length: 180, unique: true, nullable: false)]
    private string $username;

    /**
     * @var string The hashed password
     */
    #[ApiProperty(readable: false)]
    #[ORM\Column(nullable: true)]
    #[Ignore]
    private ?string $password = null;

    /**
     * Foto de perfil: ruta relativa en disco (`App\Cuenta\FotoPerfil`). No se
     * expone por la API genérica; se sube y se lee por `/api/me/foto`.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(length: 120, nullable: true)]
    #[Ignore]
    private ?string $foto = null;

    // Solo al crear: al editar, vacío significa "no cambiar la contraseña" (ver UsuarioPasswordHasher).
    #[Assert\NotBlank(groups: ["usuario:crear"])]
    private ?string $plainPassword = null;
    private ?string $fullName;

    #[ORM\OneToMany(mappedBy: "usuario", targetEntity: ApiToken::class)]
    private Collection $apiTokens;

    /**
     * Roles concedidos con su alcance (ADR-039). Se escriben por
     * `/api/acceso`, nunca por la API genérica.
     *
     * @var Collection<int, Asignacion>
     */
    #[ORM\OneToMany(mappedBy: "usuario", targetEntity: Asignacion::class)]
    #[ApiProperty(readable: false, writable: false)]
    #[Ignore]
    private Collection $asignaciones;

    /** Sin activar no entra (`App\Acceso\UsuarioActivo`). Se cambia por `/api/acceso/usuarios/{id}/activo`. */
    #[ORM\Column(options: ["default" => true])]
    #[ApiProperty(writable: false)]
    private bool $activo = true;

    /** Si es usuario de una agencia: vende con el saldo de esta (ADR-021). */
    #[ORM\ManyToOne]
    private ?Agencia $agencia = null;

    /**
     * Estación de trabajo: donde opera ahora (vende, chequea, recibe). La
     * fija el administrador y el usuario la cambia entre las de sus
     * asignaciones (`App\Acceso\EstacionTrabajo`); una fuera de su alcance
     * no le sirve para operar.
     */
    #[ORM\ManyToOne]
    private ?Estacion $estacion = null;

    public function __construct($data = [])
    {
        if (!empty($data)) {
            $this->loadData($data);
        }

        $this->apiTokens = new ArrayCollection();
        $this->asignaciones = new ArrayCollection();
    }

    public function getFullName()
    {
        return $this->nombre . " " . $this->apellido;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    #[Ignore]
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */

    /**
     * Para Symfony todos son `ROLE_USER`: lo que cada uno puede hacer lo
     * deciden sus asignaciones (`App\Acceso\CapacidadVoter`), no los roles
     * de seguridad.
     */
    #[Ignore]
    public function getRoles(): array
    {
        return [Role::user];
    }

    /** @return Collection<int, Asignacion> */
    public function getAsignaciones(): Collection
    {
        return $this->asignaciones;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFoto(): ?string
    {
        return $this->foto;
    }

    public function setFoto(?string $foto): static
    {
        $this->foto = $foto;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    // public static function createFromPayload($username, array $payload): User {
    //     return new self(
    //         $username,
    //         $payload['userRoles'] ?? [], // Added by default
    //         $payload['username'] ?? [] // Custom
    //     );
    // }

    public function __toString()
    {
        return $this->username;
    }

    /**
     * @return Collection<int, ApiToken>
     */
    public function getApiTokens(): Collection
    {
        return $this->apiTokens;
    }

    public function addApiToken(ApiToken $apiToken): static
    {
        if (!$this->apiTokens->contains($apiToken)) {
            $this->apiTokens->add($apiToken);
            $apiToken->setUsuario($this);
        }

        return $this;
    }

    public function removeApiToken(ApiToken $apiToken): static
    {
        if ($this->apiTokens->removeElement($apiToken)) {
            if ($apiToken->getUsuario() === $this) {
                $apiToken->setUsuario(null);
            }
        }
        return $this;
    }

    public function getToken(): ?ApiToken
    {
        return $this->getApiTokens()
            // ->filter(
            //     fn(ApiToken $apiToken) => $apiToken->isActivo()
            // )
            ->first() ?:
            null;
    }

    #[Ignore]
    public function getValidTokenStrings(): ?string
    {
        return $this->getApiTokens()
            ->filter(fn(ApiToken $token) => $token->isValid())
            ->map(fn(ApiToken $token) => $token->getToken())
            ->first();
    }

    public function getAgencia(): ?Agencia
    {
        return $this->agencia;
    }

    public function setAgencia(?Agencia $agencia): static
    {
        $this->agencia = $agencia;

        return $this;
    }

    public function getEstacion(): ?Estacion
    {
        return $this->estacion;
    }

    public function setEstacion(?Estacion $estacion): static
    {
        $this->estacion = $estacion;

        return $this;
    }

    public function getLabel(): string
    {
        $temp = explode(" ", $this->apellido);
        return $this->username . ": " . $this->nombre . " " . $temp[0] ??
            $this->apellido;
    }
}
