# Referencia de archivos Compose

## `compose.yaml` — Configuración base

Archivo principal que define los 3 servicios y sus configuraciones compartidas.

```yaml
services:
  backend:
    image: ${IMAGES_PREFIX:-}app-php
    container_name: backend
    depends_on:
      database:
        condition: service_healthy
    environment:
      TZ: America/Guatemala
      FRONTEND_UPSTREAM: frontend:9000
      SERVER_NAME: ${SERVER_NAME:-localhost}, backend:80
      MERCURE_PUBLISHER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET:-...}
      MERCURE_SUBSCRIBER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET:-...}
      TRUSTED_PROXIES: ${TRUSTED_PROXIES:-127.0.0.0/8,10.0.0.0/8,...}
      TRUSTED_HOSTS: ${TRUSTED_HOSTS:-^{SERVER_NAME:-example\.com|localhost}|backend$}
      DATABASE_URL: postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-...}@database:5432/${POSTGRES_DB:-app}?serverVersion=${POSTGRES_VERSION:-16}&charset=${POSTGRES_CHARSET:-utf8}
      MERCURE_URL: ${CADDY_MERCURE_URL:-http://backend/.well-known/mercure}
      MERCURE_PUBLIC_URL: ${CADDY_MERCURE_PUBLIC_URL:-https://${SERVER_NAME:-localhost}:${HTTPS_PORT:-443}/.well-known/mercure}
      MERCURE_JWT_SECRET: ${CADDY_MERCURE_JWT_SECRET:-...}
    volumes:
      - caddy_data:/data
      - caddy_config:/config
    ports:
      - target: 80
        published: ${HTTP_PORT:-80}
        protocol: tcp
      - target: 443
        published: ${HTTPS_PORT:-443}
        protocol: tcp
      - target: 443
        published: ${HTTP3_PORT:-443}
        protocol: udp
    healthcheck:
      test: curl --insecure --fail http://localhost/docs || exit 1
      timeout: 5s
      retries: 5
      start_period: 60s

  frontend:
    image: ${IMAGES_PREFIX:-}app-frontend
    container_name: frontend
    environment:
      TZ: America/Guatemala
    healthcheck:
      test: curl -f http://localhost:9000 || exit 1
      timeout: 5s
      retries: 5
      start_period: 60s

  database:
    image: postgres:${POSTGRES_VERSION:-16}-alpine
    container_name: database
    environment:
      TZ: America/Guatemala
      POSTGRES_DB: ${POSTGRES_DB:-app}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-!ChangeMe!}
      POSTGRES_USER: ${POSTGRES_USER:-app}
    healthcheck:
      test: ['CMD', 'pg_isready', '-d', '${POSTGRES_DB:-app}', '-U', '${POSTGRES_USER:-app}']
      timeout: 5s
      retries: 5
      start_period: 60s
    volumes:
      - database_data:/var/lib/postgresql/data:rw

volumes:
  caddy_data:
  caddy_config:
  database_data:
```

### Secciones clave

- **`depends_on`**: backend espera a que database esté healthy antes de iniciar
- **`healthcheck`**: cada servicio define su propia verificación de salud
- **`environment`**: uso extensivo de defaults con `${VAR:-default}` para desarrollo local
- **`ports`**: backend expone HTTP, HTTPS y HTTP/3; database expone 5432 solo en override
- **`volumes`**: datos persistentes para Caddy (certificados TLS) y PostgreSQL

## `compose.override.yaml` — Desarrollo

Este archivo se aplica automáticamente sobre `compose.yaml` cuando se ejecuta `docker compose up`.

```yaml
services:
  backend:
    build:
      context: ./backend
      target: frankenphp_dev     # Etapa de desarrollo con Xdebug y watch
    volumes:
      - ./backend:/app           # Bind mount del código fuente
      - /app/var                 # Excluir var/ del bind mount
      - ./backend/frankenphp/Caddyfile.dev:/etc/frankenphp/Caddyfile:ro
      - ./backend/frankenphp/conf.d/20-app.dev.ini:/usr/local/etc/php/app.conf.d/20-app.dev.ini:ro
    environment:
      MERCURE_EXTRA_DIRECTIVES: demo
      XDEBUG_MODE: '${XDEBUG_MODE:-off}'
    extra_hosts:
      - host.docker.internal:host-gateway
    tty: true

  frontend:
    build:
      context: ./frontend
      target: dev
    volumes:
      - ./frontend:/frontend
      - ./frontend/node_modules:/frontend/node_modules
    environment:
      API_PLATFORM_CREATE_CLIENT_ENTRYPOINT: http://backend
      NODE_TLS_REJECT_UNAUTHORIZED: '0'
      WATCHPACK_POLLING: 'true'

  database:
    mem_limit: 512m
    cpus: 0.5
    ports:
      - target: 5432
        published: 5432
        protocol: tcp
```

### Diferencias con compose.yaml

| Característica | compose.yaml | compose.override.yaml |
|---|---|---|
| Imagen | Pre-construida | Build local con target dev |
| Código | Contenido en la imagen | Bind mount (edición en caliente) |
| Xdebug | No configurado | Variable y Caddyfile.dev |
| Frontend | Imagen pre-construida | Build local con hot-reload |
| DB puertos | No expuestos | Expuesto en :5432 |
| Límites | Sin límites | CPU/Memoria acotados |

## `compose.prod.yaml` — Producción

Se aplica explícitamente con `-f compose.yaml -f compose.prod.yaml`.

```yaml
services:
  backend:
    build:
      context: ./backend
      target: frankenphp_prod   # Etapa de producción optimizada
    environment:
      APP_SECRET: ${APP_SECRET}
      MERCURE_PUBLISHER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET}
      MERCURE_SUBSCRIBER_JWT_KEY: ${CADDY_MERCURE_JWT_SECRET}

  frontend:
    build:
      context: ./frontend
      target: prod

  database:
    environment:
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
```

### Diferencias con desarrollo

| Aspecto | Dev | Prod |
|---|---|---|
| Build target | `frankenphp_dev` | `frankenphp_prod` |
| PHP | `php.ini-development` | `php.ini-production` |
| Xdebug | Disponible | Ausente |
| Frontend | Hot-reload (Vite) | Build estático |
| Variables | Defaults locales | Requeridas explícitamente |
| APP_SECRET | Opcional | Obligatorio |
| JWT secret | Default inseguro | Obligatorio |
| DB password | Default `!ChangeMe!` | Obligatorio |

## Jerarquía de Merge

Docker Compose aplica las configuraciones en este orden:

1. `compose.yaml` (base)
2. `compose.override.yaml` (automático en `up`)
3. Archivos adicionales con `-f` (último tiene prioridad)

Para producción se omite el override explícitamente:

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d
```
