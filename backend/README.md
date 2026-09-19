# Backend — FDN Transportes

API Symfony 8 + API Platform con soporte REST y GraphQL.

## Stack

- PHP 8.4+
- Symfony 8.1
- API Platform 4.2
- Doctrine ORM 3.x
- PostgreSQL 16
- FrankenPHP (Caddy + Mercure)

## Inicio rápido

```bash
# Iniciar servicios
docker compose up -d backend database

# Comandos útiles
docker compose exec backend php bin/console cache:clear
docker compose exec backend php bin/console doctrine:migrations:migrate -n
docker compose exec backend php bin/console debug:router
```

## Documentación

No hay sitio MkDocs. La documentación vigente del backend vive en:

- `AGENTS.md` (este directorio) — reglas Symfony/Doctrine/GraphQL/testing.
- `../AGENTS.md` (raíz) — terminología de dominio y estado actual del modelo de datos.
- `../CONTEXT.md` — glosario de dominio.
- `../docs/architecture/decisions/` — ADRs (incluye Dual Entity Manager, Flat Permission Set, estrategia de migración legacy).

## Estructura

| Directorio | Propósito |
|---|---|
| `src/Entity/` | Entidades del dominio (30) |
| `src/EntitySistemaFdn/` | Entidades legacy SQL Server (112) |
| `src/Security/` | IAM: Voters, PermissionManager |
| `src/Migration/` | Migración de datos desde TerminalOmnibus |
| `src/Command/` | Comandos Symfony |
| `config/packages/` | Configuración Symfony |
| `migrations/` | Migraciones Doctrine |
| `frankenphp/` | Configuración Caddy/FrankenPHP |
