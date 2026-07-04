# Troubleshooting Docker

## Conflictos de puertos

### Puerto 80/443 ya en uso

```bash
# Error: port is already allocated

# Solución 1: Detener el servicio que ocupa el puerto
sudo lsof -i :80
sudo systemctl stop nginx  # o apache2, caddy, etc.

# Solución 2: Usar puertos alternativos
HTTP_PORT=8080 HTTPS_PORT=8443 make dev
```

### Puerto 5432 ya en uso

```bash
# Error: port 5432 is already allocated

# Solución: detener PostgreSQL local
sudo systemctl stop postgresql

# O cambiar el puerto mapeado en compose.override.yaml:
# ports:
#   - target: 5432
#     published: 5433  # Cambiar aquí
```

## Conexión a base de datos

### Backend no puede conectar a PostgreSQL

```bash
# Verificar que database esté healthy
docker compose ps database
docker compose logs database

# Verificar credenciales
docker compose exec database psql -U app -d app

# Si no funciona, revisar DATABASE_URL en backend:
docker compose exec backend env | grep DATABASE_URL

# Probar conexión desde backend
docker compose exec backend php -r "
  try {
    new PDO('pgsql:host=database;port=5432;dbname=app', 'app', getenv('POSTGRES_PASSWORD'));
    echo 'OK';
  } catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
  }
"
```

### Healthcheck siempre falla

```bash
# Ver healthcheck del backend
docker inspect --format "{{json .State.Health }}" backend | jq

# Si falla, revisar que el contenedor esté sirviendo en :80
docker compose exec backend curl -s -o /dev/null -w "%{http_code}" http://localhost/docs

# Aumentar start_period en compose.yaml si la app tarda en arrancar
```

## Caché y permisos

### Permisos denegados en var/cache

```bash
# Error: Unable to write to var/cache

# Solución: corregir permisos desde el host
sudo chown -R $USER:$USER backend/var/
sudo chmod -R 777 backend/var/

# O desde el contenedor
docker compose exec backend chmod -R 777 var/
```

### Symfony en modo mantenimiento o ruta no encontrada

```bash
# Limpiar caché
docker compose exec backend php bin/console cache:clear

# Warmup
docker compose exec backend php bin/console cache:warmup

# Ver rutas disponibles
docker compose exec backend php bin/console debug:router
```

## Frontend

### Frontend no conecta con backend

```bash
# Verificar que backend esté accesible
docker compose exec frontend curl -s -o /dev/null -w "%{http_code}" http://backend/api

# Verificar variable API_PLATFORM_CREATE_CLIENT_ENTRYPOINT
docker compose exec frontend env | grep API_PLATFORM

# En desarrollo, verificar NODE_TLS_REJECT_UNAUTHORIZED=0
```

### Error de módulos/importaciones en frontend

```bash
# Reinstalar dependencias
docker compose exec frontend npm install

# Reconstruir frontend
docker compose up -d --build frontend
```

## Problemas con imágenes

### Error de compilación de backend

```bash
# Limpiar caché de Docker
docker builder prune -f

# Reconstruir sin caché
docker compose build --no-cache backend
```

### Error de espacio en disco

```bash
# Liberar espacio
docker system prune -a --volumes

# Ver uso de disco
docker system df
```

## Migración

### Error de conexión a SQL Server

```bash
# Verificar variables de entorno de conexión legada
docker compose exec backend env | grep systemfdn

# Probar conexión desde backend
docker compose exec backend php -r "
  try {
    new PDO('dblib:host=\$host;port=\$port;dbname=\$dbname', '\$user', '\$password');
    echo 'OK';
  } catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
  }
"
```

## Mensajes de error comunes

| Error | Causa | Solución |
|---|---|---|
| `port is already allocated` | Puerto en uso por otro proceso | Cambiar puerto o detener el proceso |
| `connection to server at "database" failed` | DB no iniciada o credenciales incorrectas | Verificar healthcheck y DATABASE_URL |
| `Unable to write to var/cache` | Permisos incorrectos | `chmod -R 777 backend/var/` |
| `Class "App\..." not found` | Autoload desactualizado | `docker compose exec backend composer dump-autoload` |
| `The "CADDY_MERCURE_JWT_SECRET" is missing` | Variable no definida | Configurar `CADDY_MERCURE_JWT_SECRET` en el entorno |
| `Host key verification failed` | Conexión SSH fallida | Verificar red y credenciales |
