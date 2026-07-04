# Tests de integración

## Patrones

Los tests de integración verifican la interacción entre componentes, incluyendo Doctrine, la base de datos y servicios.

### Test con Doctrine

```php
class UsuarioRepositoryTest extends KernelTestCase {
    private EntityManagerInterface $entityManager;
    private UsuarioRepository $repository;

    protected function setUp(): void {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager
            ->getRepository(Usuario::class);
    }

    public function testFindByUsername(): void {
        $user = new Usuario();
        $user->setUsername('testuser');
        $user->setNombre('Test');
        $user->setApellido('User');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $found = $this->repository->findOneBy([
            'username' => 'testuser'
        ]);

        $this->assertNotNull($found);
        $this->assertEquals('testuser', $found->getUsername());
    }
}
```

### Test de PermissionManager con datos reales

```php
class PermissionManagerIntegrationTest extends KernelTestCase {
    public function testEffectiveActions(): void {
        // Crear roles y permisos
        $action = new Action();
        $action->setCodigo('test.ver');
        // ...

        $permiso = new Permiso();
        $permiso->setNombre('Test Permiso');
        $permiso->addAction($action);
        // ...

        $role = new Role();
        $role->setNombre('ROLE_TEST');
        $role->addPermiso($permiso);
        // ...

        $user = new Usuario();
        $user->setUsername('test');
        $user->addUserRole($role);
        // ...

        $manager = static::getContainer()->get(PermissionManager::class);
        $this->assertTrue($manager->can($user, 'test.ver'));
    }
}
```

### Test de migración

```php
class MigradorIntegrationTest extends KernelTestCase {
    public function testMigrarServicio(): void {
        $migrador = static::getContainer()->get(Migrador::class);
        $contadores = $migrador->migrarServicio(1);

        $this->assertArrayHasKey('servicio', $contadores);
        $this->assertArrayHasKey('boleto', $contadores);
    }
}
```

## Database Transactions

```php
use Doctrine\DBAL\Connection;

class DatabaseTest extends KernelTestCase {
    public function testConnection(): void {
        $connection = static::getContainer()->get(Connection::class);
        $this->assertTrue($connection->isConnected());
    }
}
```

## Fixtures en tests

Los fixtures de datos (ver `src/DataFixtures/`) se pueden cargar en tests:

```php
class UserFixturesTest extends KernelTestCase {
    public function testFixturesLoad(): void {
        $loader = new Loader();
        $loader->addFixture(new UserFixtures(
            $this->createMock(UserPasswordHasherInterface::class)
        ));
        $loader->load($this->entityManager);
    }
}
```
