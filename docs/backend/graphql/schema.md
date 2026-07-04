# Schema GraphQL

## Generación

El schema GraphQL se genera automáticamente por API Platform 4.3 a partir de:
- Atributos `#[ApiResource]` en entidades y DTOs
- Atributos `#[ApiFilter]` para filtros
- Resolvedores personalizados registrados en las operaciones

## Tipos generados automáticamente

Cada entidad produce:

```graphql
type Usuario {
    id: ID!
    username: String!
    nombre: String
    apellido: String
    email: String
    telefono: String
    userRoles: [Role]
    permisos: [Permiso]
    directActions: [Action]
    deniedActions: [Action]
    createdAt: DateTime
    updatedAt: DateTime
}
```

## Input types

Para cada mutation se generan input types:

```graphql
input UsuarioInput {
    username: String
    nombre: String
    apellido: String
    email: String
    password: String
    userRoles: [ID]
}

input UsuarioUpdateInput {
    username: String
    nombre: String
    apellido: String
    email: String
}
```

## Operaciones por entidad

Para cada entidad con `#[ApiResource]`:

| Operación | Nombre por defecto | Descripción |
|-----------|-------------------|-------------|
| `Query` | `{entity}` | Obtener un ítem por ID |
| `QueryCollection` | `{entity}Collection` | Listar colección |
| `Mutation` | `create{Entity}` | Crear entidad |
| `Mutation` | `update{Entity}` | Actualizar entidad |
| `Mutation` | `delete{Entity}` | Eliminar entidad |

## Operaciones personalizadas

Además de las operaciones estándar, se definen operaciones explícitas en los atributos:

```php
#[ApiResourcePaginationPage(
    graphQlOperations: [
        new Query(),
        new Mutation(name: 'create', processor: UsuarioPasswordHasher::class),
        new Mutation(name: 'update', processor: UsuarioPasswordHasher::class),
        new QueryCollection(...),
        new Query(
            name: 'getByUsername',
            resolver: UserByUsernameResolver::class,
            args: ['username' => ['type' => 'String']],
        ),
    ]
)]
```

## Schema personalizado

Entidades con `#[ApiResourceNoPagination]` (catálogos pequeños):
- `role`, `permiso`, `action`, `status`, `icon`, `iconCategory`, `empresa`, `busMarca`, `apiToken`

Entidades con `#[ApiResourcePaginationPage]` (alto volumen):
- `usuario`, `boleto`, `servicio`, `venta`, `recorrido`, `trayecto`, `bus`, `asiento`, `cliente`, `enclave`, `localidad`, `nacion`, `piloto`

## Exportación

Para exportar el schema:

```bash
docker compose exec backend php bin/console api:graphql:export > schema.graphql
```

## Tipos personalizados

Además de los tipos generados, se registran tipos escalares personalizados:

- `DateTime`: formato ISO 8601
- `JSON`: valores JSON arbitrarios
- `UUID`: identificadores UUID
- `Money`: cantidad monetaria con moneda

## Configuración de paginación

```yaml
api_platform:
    graphql:
        collection:
            pagination:
                enabled: true
    defaults:
        pagination_client_items_per_page: true
        collection:
            pagination:
                enabled_parameter_name: pagination
```
