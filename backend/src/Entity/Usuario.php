<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Attribute\ApiResourcePaginationPage;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Base\PersonaBase;
use App\Filter\IdPartialSearchFilter;
use App\Repository\UsuarioRepository;
use App\Resolver\UserByUsernameResolver;
use App\Services\Collection as ServicesCollection;
use App\Services\UsuarioPasswordHasher;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ApiResourcePaginationPage(
    graphQlOperations: [
        new Query(),
        new Mutation(name: 'create', processor: UsuarioPasswordHasher::class),
        new Mutation(name: 'update', processor: UsuarioPasswordHasher::class),
        new QueryCollection(
            paginationType: 'page',
            parameters: [
                'id' => new QueryParameter(
                    filter: new OrFilter(new IdPartialSearchFilter()),
                    property: 'id',
                ),
                'username' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'username'
                ),
                'nombre' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'nombre'
                ),
                'apellido' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'apellido'
                ),
                'nit' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'nit'
                ),
                'email' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'email'
                ),
            ],
        ),
        new Query(
            name: 'getByUsername',
            resolver: UserByUsernameResolver::class,
            args: ['username' => ['type' => 'String']],
        ),
    ]
)]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt'])]
#[ApiFilter(SearchFilter::class, properties: ['permisos.id' => 'exact', 'userRoles.id' => 'exact', 'localidad.id' => 'exact', 'status.id' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'nombre', 'apellido', 'username', 'createdAt', 'email'], arguments: ['orderParameterName' => 'order'])]

class Usuario extends PersonaBase implements UserInterface, PasswordAuthenticatedUserInterface {

    #[ORM\Column(length: 180, unique: true, nullable: false)]
    private string $username;

    /**
     * @var string The hashed password
     */
    #[ApiProperty(readable: false)]
    #[ORM\Column(nullable: true)]
    #[Ignore]
    private ?string $password = null;

    #[Assert\NotBlank()]
    private ?string $plainPassword = null;
    private ?string $fullName;

    #[ORM\OneToMany(mappedBy: 'usuario', targetEntity: ApiToken::class)]
    private Collection $apiTokens;

    #[ORM\JoinTable(name: 'user_role')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\ManyToMany(targetEntity: Role::class)]
    private Collection $userRoles;

    /**
     * @var Collection<int, Permiso>
     */
    #[ORM\ManyToMany(targetEntity: Permiso::class)]
    private Collection $permisos;

    /**
     * @var Collection<int, Action>
     */
    #[ORM\ManyToMany(targetEntity: Action::class)]
    #[ORM\JoinTable(name: 'user_direct_action')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'action_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $directActions;

    /**
     * @var Collection<int, Action>
     */
    #[ORM\ManyToMany(targetEntity: Action::class)]
    #[ORM\JoinTable(name: 'user_denied_action')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'action_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $deniedActions;

    public function __construct($data = []) {

        if (!empty($data)) {
            $this->loadData($data);
        }

        $this->apiTokens = new ServicesCollection();
        $this->userRoles = new ServicesCollection();
        $this->permisos = new ServicesCollection();
        $this->directActions = new ServicesCollection();
        $this->deniedActions = new ServicesCollection();
    }

    public function getFullName() {
        return $this->nombre . ' ' . $this->apellido;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function setUsername(string $username): static {
        $this->username = $username;

        return $this;
    }

    #[Ignore]
    public function getUserIdentifier(): string {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */

    public function getUserRoles(): Collection {
        return $this->userRoles;
    }
    #[Ignore]
    public function getRoles(): array {
        return $this->userRoles->map(fn(Role $role) => $role->getNombre())->toArray();
    }


    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string {
        return $this->password;
    }

    public function setPassword(string $password): static {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self {
        $this->plainPassword = $plainPassword;

        return $this;
    }


    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void {
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

    public function __toString() {
        return $this->username;
    }

    /**
     * @return Collection<int, ApiToken>
     */
    public function getApiTokens(): Collection {
        return $this->apiTokens;
    }

    public function addApiToken(ApiToken $apiToken): static {
        if (!$this->apiTokens->contains($apiToken)) {
            $this->apiTokens->add($apiToken);
            $apiToken->setUsuario($this);
        }

        return $this;
    }

    public function removeApiToken(ApiToken $apiToken): static {
        if ($this->apiTokens->removeElement($apiToken)) {
            if ($apiToken->getUsuario() === $this) {
                $apiToken->setUsuario(null);
            }
        }
        return $this;
    }

    public function getToken(): ?ApiToken {
        return $this->getApiTokens()
            // ->filter(
            //     fn(ApiToken $apiToken) => $apiToken->isActivo()
            // )
            ->first() ?: null;
    }

    #[Ignore]
    public function getValidTokenStrings(): ?string {
        return $this->getApiTokens()
            ->filter(fn(ApiToken $token) => $token->isValid())
            ->map(fn(ApiToken $token) => $token->getToken())
            ->first();
    }


    public function addUserRole(Role $role): static {
        if (!$this->userRoles->contains($role)) {
            $this->userRoles->add($role);
        }

        return $this;
    }

    public function removeUserRole(Role $role): static {
        $this->userRoles->removeElement($role);

        return $this;
    }

    /**
     * @return Collection<int, Permiso>
     */
    public function getPermisos(): Collection {
        return $this->permisos;
    }

    public function addPermiso(Permiso $permiso): static {
        if (!$this->permisos->contains($permiso)) {
            $this->permisos->add($permiso);
        }

        return $this;
    }

    public function removePermiso(Permiso $permiso): static {
        $this->permisos->removeElement($permiso);

        return $this;
    }

    /**
     * @return Collection<int, Action>
     */
    public function getDirectActions(): Collection {
        return $this->directActions;
    }

    public function addDirectAction(Action $action): static {
        if (!$this->directActions->contains($action)) {
            $this->directActions->add($action);
        }

        return $this;
    }

    public function removeDirectAction(Action $action): static {
        $this->directActions->removeElement($action);

        return $this;
    }

    /**
     * @return Collection<int, Action>
     */
    public function getDeniedActions(): Collection {
        return $this->deniedActions;
    }

    public function addDeniedAction(Action $action): static {
        if (!$this->deniedActions->contains($action)) {
            $this->deniedActions->add($action);
        }

        return $this;
    }

    public function removeDeniedAction(Action $action): static {
        $this->deniedActions->removeElement($action);

        return $this;
    }

    public function getLabel() {
        $temp = explode(' ', $this->apellido);
        return $this->username . ': ' . $this->nombre . ' ' . $temp[0] ?? $this->apellido;
    }
}
