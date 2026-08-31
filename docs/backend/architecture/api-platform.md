# API Platform

## Configuración general

Archivo: `config/packages/api_platform.yaml`

API Platform 4.3 está configurado con soporte dual REST + GraphQL:

```yaml
api_platform:
    title: API Transporte Fuentes del Norte (FDN)
    version: 0.2
    graphql:
        collection:
            pagination:
                enabled: true
    formats:
        jsonld: ["application/ld+json"]
        graphql: ["application/graphql"]
    defaults:
        pagination_enabled: true
        pagination_client_items_per_page: true
        route_prefix: "api"
```

## GraphQL como capa primaria

GraphQL es el protocolo principal de la API. REST se mantiene para endpoints administrativos y utilitarios.

### Operaciones GraphQL generadas

Cada entidad con atributo `#[ApiResource]` expone automáticamente:

- `Query`: obtener un ítem por ID
- `QueryCollection`: listar colección con filtros y paginación
- `Mutation(create)`: crear entidad
- `Mutation(update)`: actualizar entidad
- `Mutation(delete)`: eliminar entidad

### Atributos personalizados

En `src/Attribute/` existen tres atributos reutilizables:

| Atributo | Propósito |
|----------|-----------|
| `#[ApiResourcePaginationPage]` | Activa paginación tipo `page` para todas las operaciones GraphQL con filtros OR por campo. Usado en entidades de alto volumen (Boleto, Salida, Venta, Usuario). |
| `#[ApiResourceNoPagination]` | Desactiva paginación. Usado en entidades pequeñas y catálogos (Role, Permiso, Action, Status, Icon, Empresa, BusMarca). |
| `#[ApiResourceBase]` | Configuración base mínima para entidades simples. |

### Filtros

#### Filtros de Doctrine ORM

| Filtro | Propósito | Archivo |
|--------|-----------|---------|
| `OrFilter` | Búsqueda OR entre múltiples campos (id, nombre, email, etc.) | `src/Filter/OrFilter.php` |
| `IdPartialSearchFilter` | Búsqueda parcial por ID con conversión automática | `src/Filter/IdPartialSearchFilter.php` |
| `SearchFilter` | Filtro exacto estándar de API Platform | — |
| `PartialSearchFilter` | Búsqueda parcial estándar de API Platform | — |
| `DateFilter` | Filtro por rango de fechas | — |
| `OrderFilter` | Ordenamiento por campos | — |
| `ExactFilter` | Coincidencia exacta (usado en EntityConfiguration.entityClass) | — |

#### Ejemplo de filtros OR en Usuario

```php
#[ApiResourcePaginationPage(
    graphQlOperations: [
        new QueryCollection(
            paginationType: 'page',
            parameters: [
                'username' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'username'
                ),
                'nombre' => new QueryParameter(
                    filter: new OrFilter(new PartialSearchFilter()),
                    property: 'nombre'
                ),
            ],
        ),
    ]
)]
```

### DTOs y ApiResources

En `src/DTO/`:

| DTO | Uso |
|-----|-----|
| `CollectionDTO` | Respuesta genérica para colecciones de metadatos |
| `DeleteMultipleDTO` | Input para eliminación masiva |
| `MetadataDTO` | Metadatos de entidad para el frontend dinámico |
| `EntityConfigurationDto` | DTO para configuración de entidad |
| `DTOBase` | Clase base con trait DataLoader |

En `src/ApiResource/`:

| Clase | Propósito |
|-------|-----------|
| `Agnostic` | Recurso agnóstico para resolver colecciones dinámicas |
| `ConfigVersions` | Versiones de configuración de entidades |
| `ConfigVersionsProvider` | Provider que expone las versiones |
| `EntityConfigurationDto` | DTO para transportar configuración |

### Paginación

- **Tipo `page`**: usado en entidades transaccionales (Boleto, Salida, Venta, Usuario). Parámetros: `currentPage`, `itemsPerPage`.
- **Sin paginación**: usado en catálogos pequeños (Role, Permiso, Action, Status, Icon, Empresa, BusMarca).

### Formatos de entrada/salida

- `application/ld+json` (JSON-LD) — REST
- `application/graphql` — GraphQL
- `application/vnd.openapi+json` — OpenAPI docs
- `text/html` — Documentación HTML

### CORS

Configurado via `nelmio_cors.yaml` para permitir peticiones cross-origin desde el frontend.
