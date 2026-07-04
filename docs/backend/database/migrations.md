# Migraciones de Doctrine

## Flujo de trabajo

Las migraciones de Doctrine se utilizan para gestionar cambios en el esquema de PostgreSQL.

### Generar una migración

```bash
docker compose exec backend php bin/console doctrine:migrations:diff
```

### Revisar la migración generada

Siempre revisar el SQL generado antes de ejecutarlo:

```bash
docker compose exec backend php bin/console doctrine:migrations:diff --dump-sql
```

### Ejecutar migraciones

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
```

### Revertir una migración

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate prev
```

## Configuración

Archivo: `config/packages/doctrine_migrations.yaml`

```yaml
doctrine_migrations:
    migrations_paths:
        'DoctrineMigrations': '%kernel.project_dir%/migrations'
    enable_profiler: false
```

Las migraciones se almacenan en el directorio `migrations/` con namespace `DoctrineMigrations`.

## Políticas

1. **Nunca modificar migraciones ya ejecutadas**: Crear una nueva migración en lugar de editar una existente.
2. **Revisar SQL generado**: `doctrine:migrations:diff` puede generar SQL subóptimo. Revisar siempre.
3. **Una migración por cambio lógico**: No acumular múltiples cambios en una sola migración.
4. **Named migrations**: Usar nombres descriptivos (e.g. `Version20240101000000`).
5. **Probar en entorno local**: Ejecutar la migración en desarrollo antes de subir a producción.

## Tabla de control

Doctrine mantiene una tabla `doctrine_migration_versions` que registra qué migraciones se han ejecutado y cuándo.

## Migraciones vs. Pipeline de migración de datos

Es importante distinguir:

| Tipo | Propósito | Herramienta |
|------|-----------|-------------|
| **Schema migration** | Cambiar estructura de tablas (columnas, índices, constraints) | Doctrine migrations (`doctrine:migrations:diff`) |
| **Data migration** | Poblar datos desde el sistema legacy TerminalOmnibus | Pipeline custom (`app:migrar:todo`, `app:migrar:estaticos`, etc.) |

Las migraciones de schema se versionan en `migrations/`. La migración de datos usa servicios en `src/Migration/`.

## Comandos útiles

```bash
# Estado actual de las migraciones
php bin/console doctrine:migrations:status

# Listar migraciones
php bin/console doctrine:migrations:list

# Ejecutar hasta una versión específica
php bin/console doctrine:migrations:migrate Version20240101000000

# Marcar como ejecutada sin ejecutar
php bin/console doctrine:migrations:version --add --all
```

## Consideraciones

- Las migraciones pueden tardar en tablas grandes (boleto, venta). Considerar migraciones batch.
- Usar transacciones para migraciones atómicas.
- Para cambios que requieren backfill de datos, combinar migración de schema + migración de datos en el pipeline.
