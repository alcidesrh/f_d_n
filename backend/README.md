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
- `../docs/architecture/decisions/` — ADRs (accesos con alcance, sincronización con el legado, latencia e índices).

## Estructura

| Directorio | Propósito |
|---|---|
| `src/Entity/` | Entidades del dominio |
| `src/Acceso/`, `src/Security/` | Accesos con alcance (ADR-039) y autenticación por Bearer |
| `src/Sincronizacion/` | Sincronización temporal con el legado (ADR-042) y su motor de importación |
| `src/Command/` | Comandos Symfony |
| `config/packages/` | Configuración Symfony |
| `migrations/` | Migraciones Doctrine |
| `frankenphp/` | Configuración Caddy/FrankenPHP |
