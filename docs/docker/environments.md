# Entornos Docker

## Tabla de variables de entorno

### Variables generales (compartidas entre entornos)

| Variable | Default | Descripción |
|---|---|---|
| `SERVER_NAME` | `localhost` | Nombre del servidor para Caddy/routing |
| `HTTP_PORT` | `80` | Puerto HTTP publicado |
| `HTTPS_PORT` | `443` | Puerto HTTPS publicado |
| `HTTP3_PORT` | `443` | Puerto HTTP/3 (QUIC) publicado |

### Backend

| Variable | Default | Dev | Prod | Debug | Descripción |
|---|---|---|---|---|---|
| `APP_ENV` | — | `dev` | `prod` | `dev` | Entorno de aplicación Symfony |
| `APP_SECRET` | — | *opcional* | **requerido** | *opcional* | Secreto de aplicación Symfony |
| `DATABASE_URL` | `postgresql://app:!ChangeMe!@database:5432/app?serverVersion=16&charset=utf8` | ✓ | ✓ | ✓ | Conexión a PostgreSQL |
| `SERVER_NAME` | `localhost` | `http://localhost` | `transportesfuentedelnorte.com` | `:80` | Server name para Caddy |
| `MERCURE_URL` | `http://backend/.well-known/mercure` | ✓ | ✓ | ✓ | URL interna de Mercure |
| `MERCURE_PUBLIC_URL` | `https://localhost/.well-known/mercure` | `http://localhost/.well-known/mercure` | ✓ | `http://localhost/.well-known/mercure` | URL pública de Mercure |
| `MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!` | ✓ | **requerido** | ✓ | Secreto JWT para Mercure |
| `CADDY_MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!` | ✓ | **requerido** | ✓ | Secreto JWT para Caddy/Mercure |
| `TRUSTED_PROXIES` | `127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16` | ✓ | ✓ | ✓ | IPs de proxies de confianza |
| `TRUSTED_HOSTS` | `^{SERVER_NAME}\|localhost$` | ✓ | ✓ | ✓ | Hosts de confianza |
| `XDEBUG_MODE` | `off` | `off` | — | `debug` | Modo Xdebug (dev/debug) |
| `MERCURE_EXTRA_DIRECTIVES` | — | `demo` | — | `demo` | Directivas extra de Mercure en dev |

### Frontend

| Variable | Default | Dev | Prod | Descripción |
|---|---|---|---|---|
| `API_PLATFORM_CREATE_CLIENT_ENTRYPOINT` | — | `http://backend` | — | Entrypoint de la API para scaffolding |
| `NODE_TLS_REJECT_UNAUTHORIZED` | `1` | `0` | — | Deshabilitar verificación TLS en dev |

### Database

| Variable | Default | Dev | Prod | Descripción |
|---|---|---|---|---|
| `POSTGRES_USER` | `app` | ✓ | ✓ | Usuario de PostgreSQL |
| `POSTGRES_PASSWORD` | `!ChangeMe!` | ✓ | **requerido** | Contraseña de PostgreSQL |
| `POSTGRES_DB` | `app` | ✓ | ✓ | Nombre de la base de datos |
| `POSTGRES_VERSION` | `16` | ✓ | ✓ | Versión de PostgreSQL |

## Comandos por entorno

### Desarrollo

```bash
make dev
# Equivale a:
# SERVER_NAME=http://localhost MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure APP_ENV=dev docker compose up -d
```

### Producción

```bash
make prod
# Equivale a:
# SERVER_NAME=transportesfuentedelnorte.com \
#   APP_SECRET=... \
#   CADDY_MERCURE_JWT_SECRET=... \
#   docker compose -f compose.yaml -f compose.prod.yaml up -d
```

### Debug (Xdebug)

```bash
make debug
# Equivale a:
# SERVER_NAME=:80 MERCURE_PUBLIC_URL=http://localhost/.well-known/mercure XDEBUG_MODE=debug APP_ENV=dev docker compose up -d
```

### Build de imágenes

```bash
make b
# docker compose build --pull --no-cache
```
