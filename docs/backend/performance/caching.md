# Cache

## Cache de Doctrine

Configurado en `config/packages/doctrine.yaml` para entorno de producción:

```yaml
when@prod:
    doctrine:
        orm:
            query_cache_driver:
                type: pool
                pool: doctrine.system_cache_pool
            result_cache_driver:
                type: pool
                pool: doctrine.result_cache_pool
```

| Pool | Propósito | Adapter |
|------|-----------|---------|
| `doctrine.system_cache_pool` | Metadata de entidades y DQL compilados | `cache.system` |
| `doctrine.result_cache_pool` | Resultados de consultas | `cache.app` |

### Uso en consultas DQL

```php
$query = $this->createQueryBuilder('b')
    ->leftJoin('b.cliente', 'c')->addSelect('c')
    ->getQuery()
    ->enableResultCache(3600, 'boleto_list_query');
```

## Cache de PermissionManager

El `PermissionManager` implementa un cache en memoria para evitar recalcular acciones en cada petición:

```php
private array $actionCache = [];

public function getEffectiveActions(Usuario $user): array {
    $userId = $user->getId();
    if (isset($this->actionCache[$userId])) {
        return array_keys($this->actionCache[$userId]);
    }
    // Calcular y cachear
    $this->actionCache[$userId] = $actions;
    return array_keys($actions);
}
```

## Cache de configuración

`EntityConfiguration` se cachea vía las consultas de API Platform + Doctrine result cache.

## Cache de metadata de Doctrine

Las entidades se cachean usando el pool `doctrine.system_cache_pool`, que evita re-procesar los atributos PHP en cada request.

## Cache de HTTP

API Platform tiene cache HTTP habilitado:

```yaml
api_platform:
    http_cache:
        public: true
    defaults:
        cache_headers:
            vary: ["Content-Type", "Authorization", "Origin"]
```

## Configuración de Symfony Cache

Archivo: `config/packages/cache.yaml`

```yaml
framework:
    cache:
        # App cache stores to filesystem by default
        # Redis configuration available but not active
```

Actualmente se usa filesystem para cache de aplicación. Redis está disponible como alternativa pero no activado.
