# GraphQL

## Visión general

GraphQL es el protocolo principal de la API FDN. Se sirve en el endpoint `/api/graphql` y está configurado a través de API Platform 4.3.

## Configuración

En `config/packages/api_platform.yaml`:

```yaml
api_platform:
    graphql:
        collection:
            pagination:
                enabled: true
    formats:
        graphql: ["application/graphql"]
```

## Schema

El schema GraphQL se genera automáticamente a partir de los atributos `#[ApiResource]` en las entidades y DTOs. API Platform expone automáticamente:

- Queries para cada entidad (item + collection)
- Mutations (create, update, delete)
- Filters y paginación

## Tipos personalizados

En `src/GraphQL/Type/`:

| Tipo | Archivo | Propósito |
|------|---------|-----------|
| `DateType` | `Type/Definition/DateType.php` | Tipo escalar personalizado para fechas en GraphQL |
| `MultipleType` | `Type/Definition/MultipleType.php` | Tipo para valores múltiples/selectores |
| `DateTypeConverter` | `Type/Converter/DateTypeConverter.php` | Conversor de tipos para serialización de fechas |

## Endpoints REST complementarios

REST se mantiene para operaciones administrativas y utilitarias:

| Endpoint | Método | Propósito |
|----------|--------|-----------|
| `/api/login` | POST | Autenticación usuario/contraseña |
| `/api/me/permissions` | GET | Permisos del usuario autenticado |
| `/api/change-password` | POST | Cambio de contraseña |
| `/api/users-brief` | GET | Listado resumido de usuarios |
| `/api/entity-record-counts` | GET | Conteo de registros por entidad |
| `/auth` | POST | Verificar autenticación |
| `/user-invalid` | GET | Invalidar sesión/token |

## GraphiQL

El explorador GraphiQL está disponible en `/api/graphiql` (acceso público en desarrollo).

## Consultas comunes

```graphql
# Obtener usuarios con filtros
query GetUsers($username: String, $nombre: String) {
    usuarioCollection(username: $username, nombre: $nombre) {
        edges {
            node {
                id
                username
                nombre
                apellido
                email
                userRoles {
                    nombre
                }
            }
        }
    }
}

# Obtener boletos con paginación
query GetBoletos($page: Int) {
    boletoCollection(currentPage: $page) {
        edges {
            node {
                id
                cliente { nombre }
                servicio { fecha }
                asiento { numero }
            }
        }
        paginationInfo {
            totalCount
            lastPage
        }
    }
}
```
