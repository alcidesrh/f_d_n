# Arquitectura del Backend

## Diagrama de Componentes C4

El backend de FDN Transportes sigue una arquitectura hexagonal simplificada sobre Symfony 8.1. A continuación se describe el diagrama de componentes a nivel de contenedor (C4).

```mermaid
graph TB
    subgraph "Backend (FrankenPHP / Caddy)"
        direction TB

        %% HTTP Layer
        Http[HTTP / Caddy Server]
        ApiPlatform[API Platform 4.3]

        %% Controller Layer
        Controllers[Controllers REST]
        GraphQL[GraphQL Endpoint]

        %% Security Layer
        Security[Security / IAM]
        Voters[ActionVoter + EntityVoter]
        PermissionManager[PermissionManager]
        ApiTokenHandler[ApiTokenHandler]

        %% Service Layer
        Services[Services Layer]
        Migradores[Migration Services]
        ConfigSync[EntityConfigSynchronizer]
        ConfigPub[ConfigChangePublisher]
        Replication[ReplicationService]

        %% Domain / ORM
        EntityManager[Doctrine ORM]
        EM_Default[(EntityManager Default)]
        EM_SystemFDN[(EntityManager SystemFDN)]

        %% Repository Layer
        Repositories[Repositories]

        %% Command Layer
        Commands[Symfony Commands]

        %% Resolver Layer
        Resolvers[GraphQL Resolvers]

        %% Message Queue
        Messenger[Messenger / Doctrine Transport]

        %% Mercure
        Mercure[Mercure Hub]
    end

    subgraph "Databases"
        PG[(PostgreSQL 16<br/>Datos nuevos)]
        SQLS[(SQL Server 2012<br/>Legado TerminalOmnibus)]
    end

    subgraph "External"
        Frontend[Frontend SPA<br/>Quasar + Vue 3]
        MercureHub[Mercure Hub]
    end

    %% Connections
    Http --> ApiPlatform
    ApiPlatform --> Controllers
    ApiPlatform --> GraphQL
    GraphQL --> Resolvers

    Controllers --> Services
    Resolvers --> Services
    Services --> EntityManager

    ApiPlatform --> Security
    Security --> Voters
    Voters --> PermissionManager
    ApiTokenHandler --> PermissionManager

    EntityManager --> EM_Default
    EntityManager --> EM_SystemFDN
    EM_Default --> PG
    EM_SystemFDN --> SQLS

    EntityManager --> Repositories

    Commands --> Migradores
    Migradores --> EM_Default
    Migradores --> EM_SystemFDN

    ConfigSync --> EntityManager
    ConfigPub --> Mercure
    Replication --> EntityManager

    Messenger --> PG
    Frontend --> Http
    Mercure --> MercureHub
```

## Capas de la aplicación

| Capa | Directorio | Responsabilidad |
|------|-----------|-----------------|
| **Controllers** | `src/Controller/` | Puntos finales REST personalizados (login, permissions, cambio password, listado usuarios, conteos entidades) |
| **Commands** | `src/Command/` | Comandos Symfony para migración, sincronización, reset de BD y utilidades |
| **Services** | `src/Services/` | Lógica de negocio (collection helpers, cambio de divisas, password hasher, sincronización config, eventos SSE, publicador Mercure) |
| **Repository** | `src/Repository/` | Acceso a datos por entidad (21 repositorios) |
| **Voters** | `src/Security/Voter/` | `ActionVoter` y `EntityVoter` — decisión de autorización granular |
| **Resolvers** | `src/Resolver/` | Resolvedores GraphQL (CollectionResolver, UserByUsername, UpdateEntityConfigurationFields) |
| **Migration** | `src/Migration/` | Pipeline de migración desde TerminalOmnibus (Limpiador → Mapeador → Migrador) |
| **ApiResource** | `src/ApiResource/` | DTOs y clases utilitarias para API Platform |
| **DTO** | `src/DTO/` | Data Transfer Objects para GraphQL (CollectionDTO, DeleteMultipleDTO, MetadataDTO, EntityConfigurationDto) |
| **Entity** | `src/Entity/` | 24 entidades del dominio mapeadas a PostgreSQL |
| **EntitySistemaFdn** | `src/EntitySistemaFdn/` | 112 entidades legacy mapeadas a SQL Server (solo lectura) |
| **Filter** | `src/Filter/` | Filtros personalizados para API Platform (OrFilter, IdPartialSearchFilter) |
| **GraphQL** | `src/GraphQL/Type/` | Tipos GraphQL personalizados (DateType, MultipleType) |
| **Security** | `src/Security/` | IAM completo (PermissionManager, ApiTokenHandler, ExpressionProvider, LegacyPasswordHasher) |
| **Doctrine** | `src/Doctrine/` | Driver DBLib personalizado para SQL Server, función CAST para DQL |

## Flujo de una petición GraphQL típica

```mermaid
sequenceDiagram
    participant C as Client (Frontend)
    participant F as FrankenPHP/Caddy
    participant AP as API Platform
    participant V as Voters
    participant PM as PermissionManager
    participant R as Resolver
    participant S as Service
    participant DB as PostgreSQL

    C->>F: GraphQL Request (POST /api/graphql)
    F->>AP: Route to API Platform
    AP->>AP: Parse GraphQL Query
    AP->>AP: Authentication (Access Token)
    AP->>V: Authorization (ActionVoter / EntityVoter)
    V->>PM: can(user, "boleto.ver")
    PM-->>V: true/false
    V-->>AP: Access granted
    AP->>R: Invoke resolver (if custom)
    R->>S: Business logic
    S->>DB: Doctrine Query
    DB-->>S: Results
    S-->>R: Data
    R-->>AP: Response
    AP-->>F: JSON Response
    F-->>C: GraphQL Response
```

## Entidades principales (24)

Distribuidas en 7 subdominios:

| Subdominio | Entidades |
|-----------|-----------|
| **Transporte** | Boleto, Trayecto, Recorrido, RecorridoMatrioska, Servicio, Status |
| **Flota** | Bus, BusMarca, Asiento, Piloto, Enclave |
| **Venta** | Venta, Factura, Cliente, Boleto, Servicio |
| **Personal** | Usuario, Piloto |
| **Configuración** | EntityConfiguration, CollectionFieldConfig, FormFieldConfig |
| **Infraestructura** | Icon, IconCategory, Empresa, Localidad, Estacion, Parada, Nacion |
| **Seguridad** | Usuario, Role, Permiso, Action, ApiToken |
