# Arquitectura del Sistema — FDN Transportes

## Vista Contexto (C4 — Nivel 1)

```mermaid
C4Context
  title Diagrama de Contexto — FDN Transportes

  Person(usuario, "Usuario", "Empleado de FDN: ventas, administración, operaciones")
  Person(cliente, "Cliente", "Pasajero que compra boletos")

  System_Boundary(fdn, "FDN Transportes") {
    System(frontend, "Frontend SPA", "Quasar + Vue 3 + Apollo Client")
    System(backend, "Backend API", "Symfony 8.1 + API Platform 4.3 + FrankenPHP")
  }

  System_Ext(pg, "PostgreSQL 16", "Base de datos principal")
  System_Ext(sqlsrv, "SQL Server 2012", "Base de datos legada (TerminalOmnibus)")
  System_Ext(mercure, "Mercure", "Servidor de eventos Server-Sent Events (integrado en Caddy)")

  Rel(usuario, frontend, "Usa", "HTTPS")
  Rel(cliente, frontend, "Consulta disponibilidad", "HTTPS")
  Rel(frontend, backend, "GraphQL (primario) / REST (secundario)", "HTTPS")
  Rel(frontend, mercure, "SSE — Tiempo real", "HTTPS")
  Rel(backend, pg, "Lectura/Escritura", "TCP 5432")
  Rel(backend, sqlsrv, "Solo lectura (migración)", "TCP 1433")
  Rel(mercure, backend, "Publica eventos", "HTTP")
```

## Vista Contenedores (C4 — Nivel 2)

```mermaid
C4Container
  title Diagrama de Contenedores — FDN Transportes

  Person(usuario, "Usuario", "Empleado de FDN")

  System_Boundary(frontend_boundary, "Frontend") {
    Container(quasar, "Quasar SPA", "Vue 3.5 + Pinia + Apollo Client 4", "SPA que corre en el navegador del usuario")
    Container(uno, "UnoCSS", "Framework CSS utility-first", "Estilos atómicos en el navegador")
  }

  System_Boundary(backend_boundary, "Backend") {
    Container(frankenphp, "FrankenPHP", "PHP 8.4 + Caddy", "Servidor web con worker mode y Mercure embebido")
    Container(symfony, "Symfony 8.1", "PHP", "Framework de aplicación, kernel HTTP")
    Container(api_platform, "API Platform 4.3", "PHP", "Capa REST + GraphQL, metadata, DTOs")
    Container(doctrine, "Doctrine 3.x + DBAL", "PHP", "ORM y conexiones a base de datos")
    Container(iam, "IAM — PermissionManager", "PHP", "Flat Permission Set, Voters, tokens API")
    Container(entity_config, "Entity Configuration", "PHP", "Metadatos de entidades para CRUD dinámico")
  }

  System_Ext(pg, "PostgreSQL 16", "Base de datos principal — datos del negocio")
  System_Ext(sqlsrv, "SQL Server 2012", "Base legada TerminalOmnibus — solo lectura")
  System_Ext(mercure_serv, "Mercure Hub", "Servidor SSE embebido en Caddy")
  System_Ext(redis, "Redis (pool de caché)", "Caché de Doctrine y sesiones")

  Rel(usuario, quasar, "Navega", "HTTPS")
  Rel(quasar, api_platform, "GraphQL queries/mutations", "HTTPS")
  Rel(quasar, api_platform, "REST endpoints", "HTTPS")
  Rel(quasar, mercure_serv, "SSE — eventos en tiempo real", "HTTPS")
  Rel(api_platform, symfony, "Delegación a servicios", "En proceso")
  Rel(symfony, doctrine, "Persistencia", "En proceso")
  Rel(symfony, iam, "Autorización", "En proceso")
  Rel(symfony, entity_config, "Metadatos dinámicos", "En proceso")
  Rel(doctrine, pg, "Lectura/Escritura", "TCP 5432")
  Rel(doctrine, sqlsrv, "Lectura", "TCP 1433")
  Rel(symfony, mercure_serv, "Publicar eventos", "HTTP")
  Rel(doctrine, redis, "Caché de resultados", "TCP 6379")
  UpdateLayoutConfig($c4ShapeInRow="3", $c4BoundaryInRow="2")
```

## Descripción del Sistema

**FDN Transportes** es un sistema de gestión de transporte terrestre de pasajeros construido como un monorepo con backend Symfony y frontend Quasar SPA. El sistema maneja la venta de boletos, administración de flota de autobuses, gestión de personal, rutas, horarios y estaciones para la empresa Transportes Fuentes del Norte.

### Dominios funcionales

| Subdominio | Descripción |
|---|---|
| **Transporte** | Rutas, trayectos, recorridos, horarios |
| **Flota** | Autobuses, marcas, asientos, mantenimiento |
| **Venta** | Boletos, ventas, facturación, clientes |
| **Personal** | Pilotos, usuarios, roles, permisos |
| **Configuración** | Empresas, localidades, moneda, tarifas |
| **Infraestructura** | Estaciones, paradas, enclaves |
| **Seguridad** | IAM, autenticación, tokens API |

## Decisiones Tecnológicas

| Decisión | Opción | Razón |
|---|---|---|
| Lenguaje backend | PHP 8.4 | Madurez, ecosistema Symfony, costo operativo |
| Framework | Symfony 8.1 | Estándar empresarial, bundles, comunidad |
| API | API Platform 4.3 | REST + GraphQL desde una misma configuración |
| ORM | Doctrine 3.x | Mapping robusto, soporte multi-EM |
| BD principal | PostgreSQL 16 | Licencia libre, rendimiento, extensiones |
| BD legada | SQL Server 2012 | Sistema heredado TerminalOmnibus |
| Frontend | Quasar 2 + Vue 3.5 | Framework completo, componentes Material |
| Estado | Pinia 3 | Tipado, devtools, simplicidad |
| GraphQL client | Apollo Client 4 | Caching, subscriptions, ecosistema |
| Estilos | UnoCSS | Utility-first, zero-config, rápido |
| Tiempo real | Mercure | SSE sobre Caddy, JWT, integración Symfony |
| Contenedores | Docker Compose | 3 servicios, entornos dev/prod |
| Servidor web | FrankenPHP | PHP embebido en Caddy, worker mode, Mercure nativo |

## Patrones de Comunicación

### GraphQL (primario)

El frontend se comunica con el backend mediante GraphQL como capa principal. Esto permite:

- Consultas exactas con solo los campos necesarios
- Múltiples recursos en una sola petición
- Mutaciones con tipo seguro (type-safe)
- Subscripciones vía Mercure para tiempo real

```graphql
query GetBoleto($id: ID!) {
  boleto(id: $id) {
    id
    cliente { nombre }
    asiento { numero clase }
    recorrido {
      nombre
      trayecto { origen { nombre } destino { nombre } }
    }
  }
}
```

### REST (secundario)

Endpoints REST se usan para:

- Autenticación (`POST /api/login`, `/api/logout`, `/api/auth/refresh`)
- Upload de archivos
- Endpoints de administración (cambiar password, conteos)
- Exposición de metadatos de configuración de entidades

### Server-Sent Events (Mercure)

Mercure proporciona actualizaciones en tiempo real:

- **Listas**: cuando un recurso se crea/modifica/elimina, la lista se actualiza automáticamente
- **Items**: cambios en detalle de entidades específicas
- **Notificaciones**: eventos del sistema visibles al usuario

```mermaid
sequenceDiagram
    participant U as Usuario
    participant F as Frontend SPA
    participant B as Backend API
    participant M as Mercure Hub
    participant D as PostgreSQL

    U->>F: Abre lista de boletos
    F->>B: GraphQL Query (boletos)
    B->>D: SELECT
    D-->>B: Resultados
    B-->>F: JSON (GraphQL)
    F-->>U: Renderiza tabla

    Note over F,M: Suscripción SSE
    F->>M: Conecta SSE (JWT)
    M-->>F: Event stream abierto

    U->>F: Crea nuevo boleto
    F->>B: GraphQL Mutation
    B->>D: INSERT
    B->>M: Publica evento (boleto.created)
    M-->>F: Evento SSE
    F->>F: Actualiza store
```
