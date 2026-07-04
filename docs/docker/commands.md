# Comandos Docker

## Gestión del stack

```bash
# Iniciar todos los servicios (detached)
docker compose up -d

# Iniciar con rebuild de imágenes
docker compose up -d --build

# Ver logs en vivo
docker compose logs --tail=0 --follow

# Detener servicios
docker compose down

# Detener y eliminar volúmenes (¡pérdida de datos!)
docker compose down -v

# Detener y limpiar contenedores huérfanos
docker compose down --remove-orphans

# Forzar detención
docker compose kill
```

## Gestión de imágenes

```bash
# Construir imágenes (sin caché)
docker compose build --pull --no-cache

# Ver imágenes disponibles
docker images | grep app-

# Eliminar imágenes
docker rmi app-php app-frontend
```

## Acceso a contenedores

```bash
# Shell en backend
docker compose exec backend sh

# Shell en frontend
docker compose exec frontend sh

# Shell en base de datos
docker compose exec database psql -U app -d app

# Comando directo en backend (Symfony console)
docker compose exec backend php bin/console cache:clear
```

## Comandos útiles para mantenimiento

```bash
# Cache clear de Symfony
docker compose exec backend php bin/console cache:clear

# Migraciones Doctrine
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

# Crear migración
docker compose exec backend php bin/console doctrine:migrations:diff

# Sincronizar metadatos de entidades
docker compose exec backend php bin/console app:config:sync-metadata

# Reset completo de base de datos
docker compose exec backend php bin/console app:reset-db --hard

# Tests
docker compose exec backend vendor/bin/phpunit -c phpunit.dist.xml
```

## Base de datos

```bash
# Conectar a PostgreSQL
docker compose exec database psql -U app -d app

# Listar tablas
\dt

# Describir tabla
\d+ boleto

# Exportar dump
docker compose exec database pg_dump -U app app > dump.sql

# Importar dump
cat dump.sql | docker compose exec -T database psql -U app -d app

# Ver conexiones activas
SELECT * FROM pg_stat_activity;
```

## Monitoreo y diagnóstico

```bash
# Estado de los contenedores
docker compose ps

# Ver recursos usados
docker stats

# Ver healthchecks
docker inspect --format "{{json .State.Health }}" backend

# Ver config de red
docker network inspect strangebuzz_default

# Ver logs de un servicio específico
docker compose logs backend
docker compose logs frontend
docker compose logs database

# Seguir logs de un servicio
docker compose logs --tail=50 --follow backend
```

## Makefile shortcuts

El `Makefile` contiene atajos para los comandos más frecuentes:

| Comando | Descripción |
|---|---|
| `make dev` | Iniciar stack de desarrollo |
| `make debug` | Iniciar con Xdebug habilitado |
| `make b` | Reconstruir imágenes |
| `make d` | Detener y limpiar contenedores |
| `make sh` | Shell en backend |
| `make logs` | Logs en vivo |
| `make cc` | Limpiar caché Symfony |
| `make migrate` | Ejecutar migraciones |
| `make test` | Ejecutar tests PHPUnit |
