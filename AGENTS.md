# AGENTS.md

Guía rápida para asistentes IA. La documentación formal completa usa MkDocs en `docs/`.

```bash
make docs-serve   # http://localhost:8000
make docs-build   # Generar site/
make docs-gen-all # Regenerar docs automáticas
```

| Área | Documento |
|---|---|
| Inicio | `docs/docs/index.md` |
| Arquitectura | `docs/docs/architecture/overview.md` |
| Decisiones (ADRs) | `docs/docs/architecture/decisions/` |
| Diagramas C4 | `docs/docs/architecture/c4/` |
| Docker | `docs/docs/docker/overview.md` |
| Makefile | `docs/docs/makefile/overview.md` |
| Tecnologías | `docs/docs/technologies.md` |
| Glosario | `docs/docs/glossary.md` |
| Frontend | `docs/docs/frontend/architecture/overview.md` |
| Backend | `docs/docs/backend/architecture/overview.md` |
| IAM | `docs/docs/backend/iam/overview.md` |
| Base de datos | `docs/docs/backend/database/overview.md` |
| Migración | `docs/docs/backend/migration/overview.md` |
| Subdominios | `docs/docs/backend/subdomains/overview.md` |

---

## Repository scope

- **Monorepo** con `backend/` (Symfony 8 + API Platform) y `frontend/` (Quasar + Vue 3)
- Orquestación vía Docker Compose (`compose.yaml` + overrides)
- Legacy: `TerminalOmnibus/` (Symfony 2.2 / SQL Server 2012, submódulo)

## Common commands

### Full stack

| Comando | Descripción |
|---|---|
| `make dev` | Start dev stack |
| `make debug` | Start with Xdebug |
| `make b` | Rebuild images |
| `make d` | Stop & remove containers |
| `make sh` | Shell en backend container |
| `make logs` | Live logs |
| `make docs-serve` | Servir documentación |
| `make docs-build` | Generar site estático |
| `make docs-gen-all` | Regenerar docs automáticas |
| `make docs-validate` | Validar enlaces y estructura |

### Backend (`backend/`)

| Comando | Descripción |
|---|---|
| `make cc` | Clear cache |
| `make migrate` | Run migrations |
| `make migration` | Create migration |
| `make entity` | Create/modify entity |
| `make test` | Run PHPUnit tests |
| `make testf F="name"` | Test by name filter |

### Frontend (`frontend/`)

| Comando | Descripción |
|---|---|
| `npm run dev` | Dev server |
| `npm run build` | Production build |
| `npm run format` | Prettier |

---

## Architecture overview

- **Runtime**: 3 servicios Docker — `backend` (FrankenPHP/Caddy), `frontend` (Quasar), `database` (PostgreSQL 16)
- **Backend**: API Platform con REST + GraphQL, dos entity managers (PG + SQL Server), CRUD dinámico vía metadatos
- **Frontend**: SPA con CRUD dinámico impulsado por GraphQL introspection + metadata del backend
- **Boot order**: unocss → api-rest → apollo → introspection → middleware → i18n → gsap
- **IAM**: Flat Permission Set con Symfony Voters + PermissionManager

---

## Practical caveats

- Root y backend `Makefile` incluyen targets legacy (phpstan, php-cs-fixer) que pueden no estar disponibles
- El frontend tiene `pnpm-lock.yaml` y `package-lock.json` — Docker usa `npm`
- La migración completa desde TerminalOmnibus toma horas (6679 salidas)
- Para debug con Xdebug: usar `make debug` (usa Caddyfile.dev sin workers)
