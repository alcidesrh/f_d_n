# IAM — Identity & Access Management

## Flat Permission Set

El sistema IAM de FDN implementa un modelo de **Flat Permission Set** (conjunto plano de permisos), donde todos los permisos de un usuario se resuelven en una lista plana de códigos de acción. No hay herencia compleja ni ACLs: cada usuario tiene un conjunto efectivo de acciones que puede realizar.

## Arquitectura del modelo

```mermaid
erDiagram
    Usuario ||--o{ ApiToken : "tiene"
    Usuario }o--o{ Role : "pertenece a"
    Usuario }o--o{ Permiso : "tiene directo"
    Usuario }o--o{ Action : "directas"
    Usuario }o--o{ Action : "denegadas"
    Role }o--o{ Role : "hereda de"
    Role }o--o{ Permiso : "agrupa"
    Role }o--o{ Action : "tiene directas"
    Permiso }o--o{ Permiso : "hereda de"
    Permiso }o--o{ Action : "contiene"

    Usuario {
        int id PK
        string username
        string nombre
        string apellido
        string email
        string password
    }
    Role {
        int id PK
        string nombre
    }
    Permiso {
        int id PK
        string nombre
        string nota
    }
    Action {
        int id PK
        string codigo
        string recurso
        string operacion
        string grupo
        string nombre
    }
    ApiToken {
        int id PK
        string token
        bool activo
        datetime expira
    }
```

## Entidades del modelo

| Entidad | Archivo | Propósito |
|---------|---------|-----------|
| `Usuario` | `src/Entity/Usuario.php` | Usuario del sistema, con credenciales y asignaciones |
| `Role` | `src/Entity/Role.php` | Rol jerárquico que agrupa permisos |
| `Permiso` | `src/Entity/Permiso.php` | Grupo de acciones relacionadas (permiso lógico) |
| `Action` | `src/Entity/Action.php` | Acción atómica con código único (e.g. `boleto.ver`) |
| `ApiToken` | `src/Entity/ApiToken.php` | Token de acceso para autenticación stateless |

## Flujo de autorización

```mermaid
flowchart TD
    A[Petición entrante] --> B{¿Tiene ROLE_SUPER_ADMIN?}
    B -->|Sí| C[Acceso total concedido]
    B -->|No| D[Resolver acciones efectivas]

    D --> E[Obtener roles del usuario]
    E --> F[Incluir roles padres recursivamente]
    F --> G[Recolectar permisos de roles]
    G --> H[+ Permisos directos del usuario]
    H --> I[Extraer acciones de todos los permisos]
    I --> J[+ Acciones directas del usuario]
    J --> K[- Acciones denegadas del usuario]
    K --> L[Set plano de acciones]

    L --> M{actionCode in set?}
    M -->|Sí| C
    M -->|No| N[Acceso denegado]
```

## Jerarquía de roles Symfony

Definida en `config/packages/security.yaml`:

```yaml
role_hierarchy:
    ROLE_SUPER_ADMIN: [ROLE_ADMIN]
    ROLE_ADMIN: [ROLE_OPERADOR, ROLE_USER]
    ROLE_OPERADOR: [ROLE_CONSULTA, ROLE_USER]
    ROLE_CONSULTA: [ROLE_USER]
```

## Evaluación de permisos

El `PermissionManager` (en `src/Security/PermissionManager.php`) sigue este orden:

1. **Roles del usuario** (incluyendo padres jerárquicos recursivamente)
2. **Permisos de los roles** → extrae todas las acciones de cada permiso
3. **Permisos directos del usuario** (bypass de roles)
4. **Acciones directas del usuario** (grants individuales)
5. **Acciones denegadas** (revocaciones individuales,优先级 más alta)

El resultado es un `array<string, bool>` cacheado en memoria por usuario.

## Ejemplo de resolución

Un usuario con:
- Role: `ROLE_OPERADOR` (que tiene permiso "Gestion Acciones")
- Permiso directo: ninguno
- Acción directa: `usuario.ver`
- Acción denegada: `boleto.anular`

Acciones efectivas: todas las acciones de "Gestion Acciones" + `usuario.ver` - `boleto.anular`
