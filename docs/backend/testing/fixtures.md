# Fixtures de test

## bootstrap.php

Archivo: `tests/bootstrap.php`

Configura el entorno de tests:

```php
// tests/bootstrap.php
require dirname(__DIR__) . '/vendor/autoload.php';

// Ensure test environment
$_SERVER['APP_ENV'] = 'test';
```

## Test database

La base de datos de test se configura via `dbname_suffix`:

```yaml
when@test:
    doctrine:
        dbal:
            dbname_suffix: "_test%env(default::TEST_TOKEN)%"
```

Esto asegura que los tests no afecten la base de datos de desarrollo.

## Cargar fixtures

```php
// En tests de integración
public function setUp(): void {
    self::bootKernel();
    $this->entityManager = self::$kernel
        ->getContainer()
        ->get('doctrine.orm.entity_manager');

    // Cargar fixtures
    $this->loadFixtures();
}

private function loadFixtures(): void {
    $loader = new Loader();
    $loader->addFixture(new ActionFixtures());
    $loader->addFixture(new PermisoFixtures());
    // ...
    $executor = new ORMExecutor($this->entityManager);
    $executor->execute($loader->getFixtures());
}
```

## Limpieza entre tests

```php
protected function tearDown(): void {
    parent::tearDown();

    $this->entityManager->close();
    $this->entityManager = null;
}
```

## Ejemplo completo

```php
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;

class FixtureAwareTestCase extends KernelTestCase {
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void {
        self::bootKernel();
        $this->entityManager = self::$kernel
            ->getContainer()
            ->get('doctrine.orm.entity_manager');
        $this->purgeDatabase();
        $this->loadAllFixtures();
    }

    private function purgeDatabase(): void {
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    private function loadAllFixtures(): void {
        $loader = self::$kernel
            ->getContainer()
            ->get(SymfonyFixturesLoader::class);
        $executor = new ORMExecutor($this->entityManager);
        $executor->execute($loader->getFixtures());
    }
}
```
