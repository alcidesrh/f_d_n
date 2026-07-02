# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

---

## Índice

1. [Contexto](#contexto)
2. [Repository scope](#repository-scope)
3. [Common commands](#common-commands)
   - [Full stack](#full-stack-from-repo-root)
   - [Backend](#backend-backend)
   - [Frontend](#frontend-frontend)
4. [Architecture overview](#architecture-overview)
   - [Runtime topology](#runtime-topology)
   - [Backend architecture](#backend-architecture-backend)
   - [Frontend architecture](#frontend-architecture-frontend)
5. [Existing local guidance files](#existing-local-guidance-files-to-honor)
6. [IAM (Identity & Access Management)](#iam-identity--access-management)
   - [Arquitectura](#arquitectura--flat-permission-set)
   - [Modelo de datos](#modelo-de-datos)
   - [Flujo de autorización](#flujo-de-autorización)
   - [Backend — archivos clave](#backend--archivos-clave)
   - [Frontend — archivos clave](#frontend--archivos-clave)
   - [Comandos relacionados con IAM](#comandos-relacionados-con-iam)
   - [Reglas IAM](#reglas-iam)
7. [Xdebug + FrankenPHP workers](#xdebug--frankenphp-workers)
8. [Migración desde TerminalOmnibus](#migración-desde-terminalomnibus-sql-server-legacy)
   - [Arquitectura](#arquitectura-1)
   - [Comando principal](#comando-principal)
   - [Flujo de migración](#flujo-de-migración)
   - [Mapeo de datos](#mapeo-de-datos)
   - [Sub-comandos útiles](#sub-comandos-útiles)
   - [Ejecución típica](#ejecución-típica)
   - [Edge cases conocidos](#edge-cases-conocidos)
   - [Archivos clave de migración](#archivos-clave-de-migración)
9. [Practical caveats](#practical-caveats)

---

## <a id="contexto"></a> Contexto

La necesidad de un sistema para le gestion administrativa, logistica, estructurar y operativa de un servicio de buses para el transporte de pasajeros a gran escala. Ademas el sistema tambien debe dar soporte a un servicio secundario de paqueteria o encomiendas las cuales se reciben y entregan en las estaciones de las rutas y su arrivo esta sujeto a la disponibilidad de espacio en la bodega del bus despues de priorizar pasajeros con equipajes. Este sistema tiene la intencion de reemplazar a uno implementado en el 2012.

## <a id="repository-scope"></a> Repository scope

- Monorepo with two main apps:
  - `backend/`: Symfony 8 + API Platform (REST + GraphQL), Doctrine, FrankenPHP.
  - `frontend/`: Quasar (Vue 3 + Pinia), dynamic CRUD UI driven by backend metadata and GraphQL introspection.
  - Root orchestration is Docker Compose (`compose.yaml`, `compose.override.yaml`, `compose.prod.yaml`) plus root `Makefile` shortcuts.
- Proyecto viejo: -`TerminalOmnibus`:
  - Symfony 2.2(php 4.5), Sqlserver 2012, Wampserver 4.3, hosting VPS con Windows Server 2022

## <a id="common-commands"></a> Common commands

### <a id="full-stack-from-repo-root"></a> Full stack (from repo root)

- Start dev stack: `make dev`
- Start with Xdebug: `make debug` or `make ud`
- Rebuild images: `make b`
- Stop and remove containers: `make d`
- View logs: `make logs`
- Open shell in backend container: `make sh`
- Open shell in frontend container: `make frontend-fdn-quasar`

### <a id="backend-backend"></a> Backend (`backend/`)

- Install PHP dependencies: `composer install`
- Start only backend stack manually: `docker compose up -d backend database`
- Clear cache: `make cc`
- Run migrations: `make migrate`
- Create migration: `make migration`
- Make entity: `make entity`
- Sync dynamic entity config metadata: `make sync-metadata`
- Run all tests: `make test`
- Run a single test by name filter: `make testf F="testMethodName"`

### <a id="frontend-frontend"></a> Frontend (`frontend/`)

- Install deps: `npm install`
- Dev server: `npm run dev`
- Production build: `npm run build`
- Format codebase: `npm run format`
- `npm run test` is currently a placeholder script (no real test runner configured).

## <a id="architecture-overview"></a> Architecture overview

### <a id="runtime-topology"></a> Runtime topology

- Compose runs three core services:
  - `backend` (FrankenPHP/Caddy, serves backend API and Mercure),
  - `frontend` (Quasar app),
  - `database` (PostgreSQL 16).
- Backend container receives `FRONTEND_UPSTREAM=frontend:9000`; frontend container is built from `frontend/Dockerfile` (`dev`/`prod` targets).

### <a id="backend-architecture-backend"></a> Backend architecture (`backend/`)

- API layer is API Platform with both REST and GraphQL enabled (`config/packages/api_platform.yaml`).
- Most entities use custom attributes in `src/Attribute/`:
  - `ApiResourceBase` injects default GraphQL operations (query/create/update/delete),
  - `ApiResourceNoPagination` / `ApiResourcePaginationPage` define collection pagination behavior.
- Security uses `Usuario` as provider and token-based auth handler (`config/packages/security.yaml`, `src/Security/`).
- Doctrine has two connections/entity managers (`config/packages/doctrine.yaml`):
  - `default` (PostgreSQL, `src/Entity`),
  - `systemfdn` (SQL Server, `src/EntitySistemaFdn` mirror models).
- Dynamic CRUD metadata is persisted in configuration entities:
  - `EntityConfiguration`, `CollectionFieldConfig`, `FormFieldConfig` in `src/Entity/Configuration/`.
  - `EntityConfigurationDto` exposes metadata to frontend via `/api/entity_configuration_dtos`.
  - `EntityConfigSynchronizer` + `app:config:sync-metadata` command keep metadata aligned with Doctrine entity fields.

### <a id="frontend-architecture-frontend"></a> Frontend architecture (`frontend/`)

- App boot sequence in `quasar.config.ts` includes: `api-rest`, `apollo`, `introspection`, `middleware` (plus UI-related boot files).
- Router (`src/router/routes.ts`) has dynamic CRUD routes:
  - `/lista/:entity` → `DynamicCollection.vue`
  - `/form/:entity/:id?` → `DynamicForm.vue`
- Route middleware (`src/boot/middleware.ts`) preloads entity store data on navigation.
- Entity model is generated at runtime:
  - `schemaStore` (`src/stores/schemaStore.ts`) introspects GraphQL schema during boot (`src/boot/introspection.ts`) and builds `entities`/`types`.
  - `entityRegistry` + `storeFactory` (`src/composables/entityRegistry.ts`, `src/stores/storeFactory.ts`) create Pinia stores on demand per entity.
- Data access is mixed:
  - GraphQL via Apollo (`src/boot/apollo.ts`) for CRUD operations,
  - REST via `useApiRest` (`src/composables/useApiRest.ts`) for config endpoints like `entity_configuration_dtos`.
- Endpoint constants live in `src/config/config.ts` (`ENTRYPOINT`, `ENTRYPOINT_GRAPHQL`).
- The Material Symbol icon library is used. Therefore, if you want to use icons with Quasar components, the name or icon attribute must have the prefix "sym*o*". There is also a component I created, Icon.vue, which renders the icon by passing the name without a prefix to the name attribute. The Icon.vue component renders an icon by passing

## <a id="existing-local-guidance-files-to-honor"></a> Existing local guidance files to honor

- `frontend/AGENTS.md`: frontend-specific conventions and dynamic CRUD notes.
- `frontend/src/form/formkit-theme-fdn/CLAUDE.md`: this subtree is a FormKit starter theme package with its own development model.
- `frontend/src/form/formkit-theme-fdn/.dmux-hooks/AGENTS.md` and `CLAUDE.md`: if editing hook scripts there, treat them as executable Bash hooks with dmux lifecycle/env-variable conventions.

## <a id="iam-identity--access-management"></a> IAM (Identity & Access Management)

### <a id="arquitectura--flat-permission-set"></a> Arquitectura — Flat Permission Set

Sistema IAM granular implementado con **Symfony Voters** + **PermissionManager** (backend) y **action codes planos** (frontend). No se usa Apache Casbin.

### <a id="modelo-de-datos"></a> Modelo de datos

```
Usuario ──ManyToMany──> Role ──ManyToMany──> Permiso ──ManyToMany──> Action
  │                      │ (jerarquía via parents/children)
  ├──ManyToMany──> Permiso (directo, bypass roles)
  ├──ManyToMany──> Action (directActions — grant override)
  └──ManyToMany──> Action (deniedActions — deny override)
```

**Action** tiene campos: `codigo` (único, ej: "boleto.crear"), `recurso` ("Boleto"), `operacion` ("create"), `grupo` ("Boletos"), `ruta` (opcional).

### <a id="flujo-de-autorización"></a> Flujo de autorización

1. **Backend**: `PermissionManager::getEffectiveActions(user)` resuelve roles → permisos → actions + directActions − deniedActions → array plano de action codes
2. **Backend**: `ActionVoter` evalúa `is_granted('boleto.crear')` contra este array. `EntityVoter` hace lo mismo para operaciones CRUD estándar.
3. **Frontend**: Al login, `GET /api/me/permissions` devuelve el array plano. `sessionStore.permissions` lo almacena. `usePermission().can(code)` hace `includes()`.
4. **Enforcement**: Backend bloquea vía Voters + `access_control` en security.yaml. Frontend oculta UI vía `v-if="can('boleto.editar')"` y route guards.

### <a id="backend--archivos-clave"></a> Backend — archivos clave

| Archivo                                     | Propósito                                                     |
| ------------------------------------------- | ------------------------------------------------------------- |
| `src/Security/PermissionManager.php`        | Resolución de acciones efectivas                              |
| `src/Security/Voter/ActionVoter.php`        | Voter por action code (`boleto.crear`)                        |
| `src/Security/Voter/EntityVoter.php`        | Voter CRUD genérico                                           |
| `src/Security/ActionExpressionProvider.php` | Función `is_granted_action()` para ExpressionLanguage         |
| `src/Controller/PermissionController.php`   | `GET /api/me/permissions`                                     |
| `config/packages/security.yaml`             | `access_control` + `access_decision_manager` + role_hierarchy |

### <a id="frontend--archivos-clave"></a> Frontend — archivos clave

| Archivo                            | Propósito                                        |
| ---------------------------------- | ------------------------------------------------ |
| `src/stores/session.ts`            | `permissions[]`, `can()`, `canAny()`, `canAll()` |
| `src/composables/usePermission.ts` | Composable `can(code)`                           |
| `src/composables/useAuth.ts`       | `login()`, `logout()`                            |
| `src/pages/auth/LoginPage.vue`     | Página de login                                  |
| `src/boot/middleware.ts`           | Route guard + auth check + perm check            |
| `src/layouts/MainLayout.vue`       | Menú filtrado por permisos                       |
| `src/types/action.ts`              | Tipo Action                                      |

### <a id="comandos-relacionados-con-iam"></a> Comandos relacionados con IAM

- Sincronizar permisos de metadata: backend resuelve automáticamente vía `PermissionManager`
- Para debug: `docker compose exec backend php bin/console debug:security`
- Para ver Voters: `docker compose exec backend php bin/console debug:container App\\Security\\Voter\\ActionVoter`

### <a id="reglas-iam"></a> Reglas IAM

- ROLE_ADMIN tiene todos los permisos (bypassea Voters)
- Los Voters usan estrategia `unanimous` (todos deben aprobar)
- Los `deniedActions` siempre anulan cualquier permiso (grant override)
- Para añadir nuevas acciones, crearlas como entidades Action con codigo único y asignarlas a Permisos via la UI de admin

## <a id="xdebug--frankenphp-workers"></a> Xdebug + FrankenPHP workers

### Problema

En dev, los workers persistentes de FrankenPHP (`worker {}` en Caddyfile) causan que Xdebug se desincronice al remover y re-agregar breakpoints sin modificar el archivo. El proceso PHP persiste entre requests y el estado DBGp (breakpoint_set / breakpoint_remove) se corrompe.

### Solución

Se usan **dos Caddyfiles**:

| Archivo                    | Propósito                                                                            |
| -------------------------- | ------------------------------------------------------------------------------------ |
| `frankenphp/Caddyfile`     | Producción — incluye `worker {}` para performance                                    |
| `frankenphp/Caddyfile.dev` | Desarrollo — **sin** bloque `worker {}` (cada request arranca un proceso PHP limpio) |

En desarrollo, `compose.override.yaml` monta `Caddyfile.dev` sobre `/etc/frankenphp/Caddyfile`:

```yaml
volumes:
  - ./backend/frankenphp/Caddyfile.dev:/etc/frankenphp/Caddyfile:ro
```

Si necesitas workers en dev (ej. probar performance), reinicia el contenedor tras togglear breakpoints.

### file_link_format para stack traces en VSCode

El `20-app.dev.ini` configura:

```ini
xdebug.file_link_format = vscode://file/%f:%l
```

Requiere `pathMappings` en `.vscode/launch.json` para traducir rutas del container al host:

- Si abrís `backend/` como raíz del workspace en VSCode:
  ```json
  "pathMappings": { "/app": "${workspaceFolder}" }
  ```
- Si abrís el repo raíz (`modelo/`):
  ```json
  "pathMappings": { "/app": "${workspaceFolder}/backend" }
  ```
  (o simpler: abrí `backend/` directamente y usá el primer mapping)

## <a id="migración-desde-terminalomnibus-sql-server-legacy"></a> Migración desde TerminalOmnibus (SQL Server legacy)

### <a id="arquitectura-1"></a> Arquitectura

| Clase | Propósito | Archivo |
|---|---|---|
| `Migrador` | Migra servicios + boletos (salida-driven) | `src/Migration/Migrador.php` |
| `MigradorEstaticos` | Migra entidades estáticas (empresa, estacion, bus, asiento, cliente, usuario, trayecto) | `src/Migration/MigradorEstaticos.php` |
| `MigradorIAM` | Migra IAM (roles, permisos, acciones) | `src/Migration/MigradorIAM.php` |
| `Mapeador` | Mapea columnas legacy → new DB | `src/Migration/Mapeador.php` |
| `Limpiador` | Trunca tablas migrables | `src/Migration/Limpiador.php` |

**Lectura**: FreeTDS PDO (`#[Target('oldPdo')]` en `config/services.yaml`).
**Escritura**: Doctrine DBAL Connection (`default`, PostgreSQL).

### <a id="comando-principal"></a> Comando principal

```bash
docker compose exec backend php bin/console app:migrar:todo [options]
```

Opciones:

| Opción | Default | Descripción |
|---|---|---|
| `--clean` | off | Limpia BD antes de migrar (trunca tablas) |
| `--skip-estaticos` | off | Salta MigradorEstaticos |
| `--skip-iam` | off | Salta MigradorIAM |
| `--skip-config` | off | Salta sync de EntityConfiguration |
| `--boletos=N` | 100 | Legacy — ya no se usa (la migración procesa todas las salidas) |

### <a id="flujo-de-migración"></a> Flujo de migración

```
Paso 1: Limpiar (opcional, --clean)
Paso 2: Estáticos   → empresas, pilotos, localidades, estaciones, clientes, usuarios,
                       marcas, buses, asientos, trayectos (incl. sub-trayectos + inversos)
Paso 3: IAM         → acciones, roles, permisos, asignaciones a usuarios
Paso 4: Config      → sincroniza EntityConfiguration metadata
Paso 5: Servicios+Boletos → por cada salida legacy:
        salida → Servicio + Recorrido + Venta(s) + Boleto(s)
```

### <a id="mapeo-de-datos"></a> Mapeo de datos

| Legacy → | New DB | Driver |
|---|---|---|
| `salida` | `servicio` (+ `recorrido`) | Migrador |
| `boleto` (por `salida_id`) | `boleto` (+ `venta`) | Migrador |
| `empresa` | `empresa` | MigradorEstaticos (+ Migrador como helper) |
| `estacion` | `enclave` | MigradorEstaticos (+ Migrador como helper) |
| `bus` | `bus` | MigradorEstaticos (+ Migrador como helper) |
| `bus_asiento` | `asiento` | MigradorEstaticos (+ Migrador como helper) |
| `cliente` | `cliente` | MigradorEstaticos (+ Migrador como helper) |
| `custom_user` | `usuario` | MigradorEstaticos (+ Migrador como helper) |
| `ruta` | `trayecto` (+ sub-trayectos) | MigradorEstaticos |
| `piloto` | `piloto` | MigradorEstaticos |
| `tarifas_boleto` | pricing en `recorrido.precio_clase_a_monto` | Migrador |

### <a id="sub-comandos-útiles"></a> Sub-comandos útiles

```bash
# Ver conexión legacy
docker compose exec backend php bin/console dbal:run-sql "SELECT COUNT(*) FROM salida" --connection=systemfdn

# Ver datos migrados
docker compose exec backend php bin/console dbal:run-sql "SELECT COUNT(*) FROM servicio" --connection=default
docker compose exec backend php bin/console dbal:run-sql "SELECT COUNT(*) FROM boleto" --connection=default

# Debug security/Voters
docker compose exec backend php bin/console debug:security
docker compose exec backend php bin/console debug:container App\\Security\\Voter\\ActionVoter

# Debug container wiring
docker compose exec backend php bin/console debug:container App\\Migration\\Migrador
```

### <a id="ejecución-típica"></a> Ejecución típica

```bash
# Migración completa (usa ~5-10 min para estáticos, horas para 6679 salidas)
# Recomendado: usar --clean para empezar desde cero
docker compose exec backend php bin/console app:migrar:todo --clean

# Saltar pasos ya ejecutados
docker compose exec backend php bin/console app:migrar:todo --skip-iam --skip-config

# Solo migrar estáticos + servicios (sin IAM ni config)
docker compose exec backend php bin/console app:migrar:todo --skip-iam --skip-config
```

### <a id="edge-cases-conocidos"></a> Edge cases conocidos

| Problema | Causa | Solución |
|---|---|---|
| ORM `getResult()` descarta cursor | `Itinerario` tiene `InheritanceType('JOINED')` → `UnitOfWork::createEntity()` ejecuta query extra | Usar raw PDO (`fetchOld()`) no Doctrine ORM |
| `Undefined array key "codigo"` | `fetchAll()` devuelve `[[...]]` pero se esperaba `[...]` | Usar `fetch()` en vez de `fetchAll()` |
| `duplicate key value` en `cliente_pkey` | Mismo cliente referenciado por múltiples boletos en misma transacción | `ON CONFLICT DO NOTHING` en INSERTs |
| Caracteres no-UTF8 | Legacy en Latin-1 | `mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1')` en `fetchOld()` |
| `BacktraceDebugDataHolder` OOM | Acumula todas las queries en memoria | `$this->resetDebugDataHolder()` periódico + `ini_set('memory_limit', '2G')` |

### <a id="archivos-clave-de-migración"></a> Archivos clave de migración

| Archivo | Rol |
|---|---|
| `src/Migration/Migrador.php` | Driver principal: salidas → servicios + boletos |
| `src/Migration/MigradorEstaticos.php` | Estáticos: empresas, buses, trayectos, etc. |
| `src/Migration/MigradorIAM.php` | IAM: roles, permisos, acciones |
| `src/Migration/Mapeador.php` | Mapeo de columnas legacy → new DB |
| `src/Migration/Limpiador.php` | Truncado de tablas migrables |
| `src/Command/MigrarTodoCommand.php` | Comando Symfony que orquesta todo |
| `config/services.yaml` | Wiring: `PDO $oldPdo` (FreeTDS) + `Connection $newConn` |
| `config/packages/doctrine.yaml` | Conexiones `default` (PG) + `systemfdn` (SQL Server vía pdo_dblib) |

## <a id="practical-caveats"></a> Practical caveats

- Root and backend `Makefile` include legacy targets (for tools like phpstan/php-cs-fixer) that are not currently present in `backend/vendor/bin`; verify tool availability before relying on those targets.
- Frontend has both `pnpm-lock.yaml` and `package-lock.json`, but Docker/dev flow in this repository uses `npm`.

## TODO
