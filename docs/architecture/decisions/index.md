# Registro de Decisiones Arquitectónicas (ADR)

> Todas las decisiones arquitectónicas significativas para el proyecto FDN Transportes.

| ADR | Título | Estado |
|-----|--------|--------|
| [ADR-001](ADR-001-api-platform-graphql.md) | API Platform + GraphQL como capa primaria de datos | Aceptada |
| [ADR-002](ADR-002-dual-entity-manager.md) | Dual Entity Manager (PostgreSQL + SQL Server legacy) | Aceptada |
| [ADR-003](ADR-003-flat-permission-set.md) | Flat Permission Set vs RBAC tradicional | Aceptada |
| [ADR-004](ADR-004-dynamic-crud-store-factory.md) | Dynamic CRUD via runtime store factory | Aceptada |
| [ADR-005](ADR-005-frankenphp-mercure.md) | FrankenPHP + Caddy + Mercure integrado | Aceptada |
| [ADR-006](ADR-006-subdomains-bounded-contexts.md) | Subdominios como bounded contexts | Aceptada |
| [ADR-007](ADR-007-formkit-custom-theme.md) | FormKit con tema personalizado + JSON schemas | Aceptada |
| [ADR-008](ADR-008-legacy-migration-strategy.md) | Estrategia de migración desde TerminalOmnibus | Aceptada |
| [ADR-009](ADR-009-autoimport-system.md) | Sistema de auto-import mediante unplugin | Aceptada |
| [ADR-010](ADR-010-dynamic-entity-config.md) | Configuración dinámica de entidades vía metadatos | Aceptada |
| [ADR-011](ADR-011-recorrido-lleva-trayecto.md) | Recorrido lleva el trayecto directamente (sin plantilla intermedia) | Aceptada |
| [ADR-012](ADR-012-trayecto-canonico-por-enclaves.md) | Trayecto canónico por par de enclaves (origen, destino) | Aceptada |
| [ADR-013](ADR-013-rename-servicio-a-recorrido.md) | Renombrar la entidad Servicio a Recorrido | Aceptada |
| [ADR-014](ADR-014-estados-tipados-por-entidad.md) | Estados tipados por entidad (enum) en vez de catálogo Status genérico | Aceptada |
| [ADR-015](ADR-015-tenant-filter.md) | TenantFilter: aislamiento por empresa vía Doctrine SQLFilter | Aceptada |
| [ADR-016](ADR-016-piloto-vuelve-a-bus.md) | La asignación de Piloto vuelve a Bus (no Recorrido); pilotoAux → copiloto | Aceptada |
