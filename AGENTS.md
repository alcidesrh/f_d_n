# AGENTS.md
This file provides guidance to WARP (warp.dev) when working with code in this repository.
## Contexto
La necesidad de un sistema para le gestion administrativa, logistica, estructurar y operativa de un   servicio de buses para el transporte de pasajeros a gran escala. Ademas el sistema tambien debe dar soporte a un servicio secundario de paqueteria o encomiendas las cuales se reciben y entregan en las estaciones de las rutas y su arrivo esta sujeto a la disponibilidad de espacio en la bodega del bus despues de priorizar pasajeros con equipajes. Este sistema tiene la intencion de reemplazar a uno implementado en el 2012.

## Repository scope
- Monorepo with two main apps:
  - `backend/`: Symfony 8 + API Platform (REST + GraphQL), Doctrine, FrankenPHP.
  - `frontend/`: Quasar (Vue 3 + Pinia), dynamic CRUD UI driven by backend metadata and GraphQL introspection.
  - Root orchestration is Docker Compose (`compose.yaml`, `compose.override.yaml`, `compose.prod.yaml`) plus root `Makefile` shortcuts. 
- Proyecto viejo: 
 -`TerminalOmnibus`: 
  - Symfony 2.2(php 4.5), Sqlserver 2012, Wampserver 4.3, hosting VPS con Windows Server 2022 

## Common commands
### Full stack (from repo root)
- Start dev stack: `make dev`
- Start with Xdebug: `make debug` or `make ud`
- Rebuild images: `make b`
- Stop and remove containers: `make d`
- View logs: `make logs`
- Open shell in backend container: `make sh`
- Open shell in frontend container: `make frontend-fdn-quasar`

### Backend (`backend/`)
- Install PHP dependencies: `composer install`
- Start only backend stack manually: `docker compose up -d backend database`
- Clear cache: `make cc`
- Run migrations: `make migrate`
- Create migration: `make migration`
- Make entity: `make entity`
- Sync dynamic entity config metadata: `make sync-metadata`
- Run all tests: `make test`
- Run a single test by name filter: `make testf F="testMethodName"`

### Frontend (`frontend/`)
- Install deps: `npm install`
- Dev server: `npm run dev`
- Production build: `npm run build`
- Format codebase: `npm run format`
- `npm run test` is currently a placeholder script (no real test runner configured).

## Architecture overview
### Runtime topology
- Compose runs three core services:
  - `backend` (FrankenPHP/Caddy, serves backend API and Mercure),
  - `frontend` (Quasar app),
  - `database` (PostgreSQL 16).
- Backend container receives `FRONTEND_UPSTREAM=frontend:9000`; frontend container is built from `frontend/Dockerfile` (`dev`/`prod` targets).

### Backend architecture (`backend/`)
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

### Frontend architecture (`frontend/`)
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

## Existing local guidance files to honor
- `frontend/AGENTS.md`: frontend-specific conventions and dynamic CRUD notes.
- `frontend/src/form/formkit-theme-fdn/CLAUDE.md`: this subtree is a FormKit starter theme package with its own development model.
- `frontend/src/form/formkit-theme-fdn/.dmux-hooks/AGENTS.md` and `CLAUDE.md`: if editing hook scripts there, treat them as executable Bash hooks with dmux lifecycle/env-variable conventions.

## Practical caveats
- Root and backend `Makefile` include legacy targets (for tools like phpstan/php-cs-fixer) that are not currently present in `backend/vendor/bin`; verify tool availability before relying on those targets.
- Frontend has both `pnpm-lock.yaml` and `package-lock.json`, but Docker/dev flow in this repository uses `npm`.
