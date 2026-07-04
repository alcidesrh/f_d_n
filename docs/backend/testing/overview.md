# Testing

## Configuración

Archivo: `phpunit.dist.xml`

PHPUnit configurado para el proyecto Symfony con soporte para Doctrine y test database.

```xml
<phpunit>
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## Ejecutar tests

```bash
# Todos los tests
docker compose exec backend php bin/phpunit

# Test específico
docker compose exec backend php bin/phpunit tests/Path/To/Test.php

# Filtrar por nombre
docker compose exec backend php bin/phpunit --filter nombreTest

# Con cobertura
docker compose exec backend php bin/phpunit --coverage-html var/coverage
```

## Base de datos de test

```yaml
when@test:
    doctrine:
        dbal:
            dbname_suffix: "_test%env(default::TEST_TOKEN)%"
```

Se usa un suffijo `_test` para aislar la base de datos de test.

## Password hasher en test

```yaml
when@test:
    security:
        password_hashers:
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: auto
                cost: 4
                time_cost: 3
                memory_cost: 10
```

En entorno test, los costos de hashing se reducen para acelerar los tests.

## Estructura de tests

```
tests/
├── bootstrap.php
├── Unit/
│   └── ...
└── Integration/
    └── ...
```

### bootstrap.php

Archivo: `tests/bootstrap.php`

Configura el entorno de test y carga el autoloader de Composer.
