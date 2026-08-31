# Comandos Symfony

Listado completo de comandos Symfony personalizados en `src/Command/`.

| Comando | Clase | Descripción |
|---------|-------|-------------|
| `app:migrar` | `MigrarCommand` | Migra boletos (salidas) desde legacy |
| `app:migrar:todo` | `MigrarTodoCommand` | Migración completa en 5 pasos |
| `app:migrar:estaticos` | `MigrarEstaticosCommand` | Migra entidades estáticas |
| `app:migrar:iam` | `MigrarIAMCommand` | Migra roles, permisos, acciones |
| `app:sincronizar` | `SincronizarCommand` | Sincronización incremental de boletos |
| `migracion` | `MigracionCommand` | Comando legacy de prueba |
| `app:sync-entity-config` | `SyncEntityConfigurationCommand` | Sincroniza EntityConfiguration |
| `app:fetch-icons` | `FetchIconsCommand` | Importa iconos desde API externa |
| `app:reset-db` | `ResetDbCommand` | Resetea la base de datos |

## Cómo ejecutar

Todos los comandos se ejecutan dentro del contenedor Docker:

```bash
docker compose exec backend php bin/console <comando> [opciones]
```
