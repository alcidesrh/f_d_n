# Profiling

## Symfony Profiler

Disponible en entorno de desarrollo en `/_profiler`. Proporciona:

- **Doctrine**: Número de consultas, tiempo, análisis N+1
- **Cache**: Hits/misses, uso de pools
- **Security**: Roles, voters evaluados, decisiones de acceso
- **Performance**: Tiempo de ejecución, memoria, tiempo en Framework/Bundle
- **Logs**: Mensajes de log, errores, warnings
- **Events**: Eventos disparados y listeners ejecutados

### Cómo usar

1. Hacer una petición a la API
2. Abrir `/_profiler/latest` en el navegador
3. Revisar la sección Doctrine para consultas lentas o N+1

## Blackfire.io

Herramienta de profiling para PHP recomendada para:

- **Perfiles de producción**: identificar cuellos de botella en endpoints reales
- **Comparación antes/después**: medir impacto de optimizaciones
- **Análisis de memoria**: identificar fugas de memoria en operaciones batch

### Integración

Blackfire se integra vía extensión PHP o Docker. Para usar:

```bash
# Desde el contenedor backend
blackfire curl https://fdn.local/api/graphql
```

## Slow Query Logging

### PostgreSQL

Configurar en `postgresql.conf`:

```ini
log_min_duration_statement = 200  # Log queries > 200ms
log_connections = on
log_disconnections = on
```

### Monitoreo de queries

```sql
-- Top 10 queries por tiempo promedio
SELECT
    query,
    calls,
    mean_time,
    total_time,
    rows
FROM pg_stat_statements
ORDER BY mean_time DESC
LIMIT 10;

-- Queries más llamadas
SELECT query, calls, rows
FROM pg_stat_statements
ORDER BY calls DESC
LIMIT 10;
```

## Logs de aplicación

Monolog configurado en `config/packages/monolog.yaml`. Niveles:

| Entorno | Nivel | Salida |
|---------|-------|--------|
| dev | DEBUG | Consola + archivo |
| prod | WARNING | Archivo |

Logs relevantes para performance:
- Consultas lentas de Doctrine (logging: true en doctrine.yaml)
- Errores de timeout o memoria
- Cache misses frecuentes

## Herramientas adicionales

| Herramienta | Uso |
|-------------|-----|
| `php bin/console debug:container --parameters` | Ver parámetros de rendimiento |
| `php bin/console doctrine:query:dql` | Probar DQL y ver SQL generado |
| `php bin/console doctrine:query:sql` | Ejecutar SQL directamente |
| `php bin/console cache:pool:list` | Listar pools de cache |

## Recomendaciones de profiling

1. **Desarrollo**: Symfony Profiler para cada request
2. **Pre-producción**: Blackfire para perfiles completos
3. **Producción**: PostgreSQL slow query log + log de aplicación
4. **Migración**: Monitorear memory usage con `memory_get_peak_usage()`
