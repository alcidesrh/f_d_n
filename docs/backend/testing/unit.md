# Tests unitarios

## Patrones

Los tests unitarios se centran en probar lógica de negocio aislada, sin depender de la base de datos.

### Test de servicios

```php
class PermissionManagerTest extends TestCase {
    private PermissionManager $manager;
    private Usuario $user;

    protected function setUp(): void {
        $this->manager = new PermissionManager();
        $this->user = new Usuario();
        // Configurar usuario mock
    }

    public function testSuperAdminHasAllPermissions(): void {
        $this->user->addUserRole(
            (new Role())->setNombre('ROLE_SUPER_ADMIN')
        );
        // Aunque no tenga acciones explícitas, ROLE_SUPER_ADMIN bypass
        $this->assertTrue(
            $this->manager->can($this->user, 'boleto.ver')
        );
    }
}
```

### Test de Voters

```php
class ActionVoterTest extends TestCase {
    private ActionVoter $voter;

    public function testSupportsDotAttribute(): void {
        $this->assertTrue(
            $this->voter->supports('boleto.ver', null)
        );
    }

    public function testDoesNotSupportSimpleAttribute(): void {
        $this->assertFalse(
            $this->voter->supports('view', null)
        );
    }
}
```

### Test de Mapeador

```php
class MapeadorTest extends TestCase {
    private Mapeador $mapeador;

    public function testEmpresaMapping(): void {
        $old = ['id' => 1, 'nombre' => 'Test', 'nit' => '123456'];
        $result = $this->mapeador->empresa($old);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Test', $result['nombre']);
    }
}
```

### Test de Entities

```php
class UsuarioTest extends TestCase {
    public function testGetFullName(): void {
        $user = new Usuario();
        $user->setNombre('Juan')->setApellido('Perez');
        $this->assertEquals('Juan Perez', $user->getFullName());
    }

    public function testRoles(): void {
        $user = new Usuario();
        $role = new Role();
        $role->setNombre('ROLE_ADMIN');
        $user->addUserRole($role);

        $this->assertCount(1, $user->getUserRoles());
        $this->assertTrue(in_array('ROLE_ADMIN', $user->getRoles()));
    }
}
```

## Mocks

Se usa PHPUnit's built-in mocking para aislar dependencias:

```php
$repository = $this->createMock(ApiTokenRepository::class);
$repository->method('findOneBy')
    ->willReturn($token);

$handler = new ApiTokenHandler($repository);
```
