# Prevención de N+1

## El problema N+1

En GraphQL, el problema N+1 ocurre cuando se hace una consulta para obtener una lista de N entidades y luego N consultas adicionales para cargar relaciones de cada entidad. Ejemplo:

```graphql
query {
    boletoCollection {
        edges {
            node {
                cliente { nombre }  # 1 consulta por cada boleto
                servicio { fecha }   # 1 consulta por cada boleto
            }
        }
    }
}
```

Sin optimización: 1 (boleto) + N (cliente) + N (servicio) = 2N+1 consultas.

## Estrategias implementadas

### 1. Doctrine JOINs con Eager Loading

Donde es apropiado, se usan `fetch: 'EAGER'` en relaciones ManyToOne:

```php
#[ORM\ManyToOne(fetch: 'EAGER')]
private ?Cliente $cliente = null;
```

**Precaución**: EAGER loading puede causar joins excesivos y duplicación de resultados.

### 2. JOIN explícitos en DQL

Para consultas batch donde se conocen las relaciones necesarias:

```php
$query = $this->createQueryBuilder('b')
    ->leftJoin('b.cliente', 'c')
    ->addSelect('c')
    ->leftJoin('b.servicio', 's')
    ->addSelect('s')
    ->getQuery();
```

### 3. Carga diferida (Lazy Loading)

Doctrine usa lazy loading por defecto, que es el origen del N+1. Se usa cuando el acceso a relaciones es condicional.

### 4. Repositorios específicos

Los repositorios personalizados implementan métodos que hacen JOINs óptimos:

```php
// BoletoRepository
public function findWithRelations(int $id): ?Boleto {
    return $this->createQueryBuilder('b')
        ->leftJoin('b.cliente', 'c')->addSelect('c')
        ->leftJoin('b.servicio', 's')->addSelect('s')
        ->leftJoin('b.asiento', 'a')->addSelect('a')
        ->leftJoin('s.recorrido', 'r')->addSelect('r')
        ->where('b.id = :id')
        ->setParameter('id', $id)
        ->getQuery()->getOneOrNullResult();
}
```

### 5. Batch processing en migración

Durante la migración, se procesan registros en lotes:

```php
// Migrador: por cada salida se migran todos sus boletos en una transacción
for ($i = 0; $i < count($boletos); $i++) {
    // Procesar boleto individual
}
```

### 6. Cache de resultados

Doctrine result cache caching reduce consultas repetitivas:

```yaml
when@prod:
    doctrine:
        orm:
            result_cache_driver:
                type: pool
                pool: doctrine.result_cache_pool
```

## Recomendaciones

1. **Usar `addSelect` en DQL** para hacer JOINs que devuelvan todos los datos en una consulta
2. **Evitar lazy loading** en listados que siempre necesitan relaciones
3. **Usar partial objects** cuando solo se necesitan campos específicos
4. **Monitorear con Symfony Profiler** para detectar N+1
5. **Implementar DataLoader pattern** para casos avanzados (futuro)

## Ejemplo: Resolver de colección

```php
// CollectionResolver: carga todas las entidades en una consulta
$allEntities = $this->entityManagerInterface
    ->getRepository($entityClass)
    ->findAll();
// findAll() es una sola consulta SQL
```

## Referencia

- [Doctrine Performance Tips](https://www.doctrine-project.org/projects/doctrine-orm/en/3.x/reference/improving-performance.html)
- [GraphQL N+1 Problem](https://shopify.engineering/solving-the-n-plus-one-problem-graphql)
