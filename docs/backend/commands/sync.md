# Comandos de sincronización

## app:sincronizar

Archivo: `src/Command/SincronizarCommand.php`

```bash
php bin/console app:sincronizar
```

Sincroniza boletos nuevos desde el sistema FDN legacy. Ideal para ejecución periódica (cron) después de la migración inicial.

### Algoritmo

```php
class SincronizarCommand extends Command {
    public function __construct(
        private Migrador $migrador,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $contadores = $this->migrador->migrarBoletosRecientes(output: $output);
        // ...
    }
}
```

Usa `Migrador::migrarBoletosRecientes()` que:
1. Lee salidas no migradas desde el legacy
2. Migra solo registros nuevos (verifica `yaMigrado()`)
3. Reporta contadores de registros insertados

### Uso en producción

```bash
# Ejecución manual
docker compose exec backend php bin/console app:sincronizar

# En cron (cada 5 minutos)
*/5 * * * * docker compose exec -T backend php bin/console app:sincronizar --no-interaction
```

## app:sync-entity-config

Archivo: `src/Command/SyncEntityConfigurationCommand.php`

Sincroniza las configuraciones de entidad con los metadatos actuales de Doctrine. Se ejecuta automáticamente como parte de `app:migrar:todo`.

## app:fetch-icons

Archivo: `src/Command/FetchIconsCommand.php`

Importa iconos desde una API externa (Lucide icons) al sistema.

## app:reset-db

Archivo: `src/Command/ResetDbCommand.php`

Resetea la base de datos (DROP SCHEMA + recreate). Útil para desarrollo y pruebas.
