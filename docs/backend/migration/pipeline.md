# Pipeline paso a paso

## Comando principal: `app:migrar:todo`

Archivo: `src/Command/MigrarTodoCommand.php`

Ejecuta la migración completa en 5 pasos:

```bash
docker compose exec backend php bin/console app:migrar:todo [options]
```

### Opciones

| Opción | Descripción |
|--------|-------------|
| `--clean` | Limpia la BD antes de migrar (DROP SCHEMA + recreate) |
| `--skip-estaticos` | Salta migración de entidades estáticas |
| `--skip-iam` | Salta migración de IAM |
| `--skip-config` | Salta sincronización de EntityConfiguration |
| `--servicios=N` | Cantidad de servicios a migrar (default: 100) |
| `--entities=[]` | Lista de entidades estáticas específicas a migrar |

### Paso 1: Reset/Clean

```php
// Hard reset: DROP SCHEMA public CASCADE + CREATE SCHEMA + SchemaTool::createSchema
// Soft reset: TRUNCATE tablas migrables + reset secuencias
$this->entityManager->getConnection()->executeStatement('DROP SCHEMA public CASCADE');
$this->entityManager->getConnection()->executeStatement('CREATE SCHEMA public');
```

### Paso 2: Datos estáticos

Ejecuta `MigradorEstaticos::migrar()` que a su vez llama a métodos individuales por entidad:
- `migrarEmpresas()`
- `migrarPilotos()`
- `migrarLocalidads()`
- `migrarEstacions()` → migra a `enclave` con tipo 'estacion'
- `migrarClientes()` (TOP 1000)
- `migrarUsuarios()` (con creación de api_token)
- `migrarMarcas()` (BusMarca)
- `migrarBuss()`
- `migrarAsientos()`
- `migrarTrayectos()` (incluye subtrayectos e inversos)

### Paso 3: IAM

Ejecuta `MigradorIAM::migrar()`:
1. `crearActionsBase()` — 25 acciones predefinidas
2. `crearPermisosBase()` — 5 permisos con sus acciones
3. `crearRolesBase()` — ROLE_ADMIN, ROLE_OPERADOR, ROLE_CONSULTA
4. `migrarRolesLegacy()` — roles del legacy
5. `asignarRolesUsuarios()` — mapeo desde legacy

### Paso 4: EntityConfiguration

```php
foreach ($metadataFactory->getAllMetadata() as $metadata) {
    if (!$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass) {
        $shortName = $metadata->getReflectionClass()->getShortName();
        if (!in_array($shortName, ['EntityConfiguration', 'CollectionFieldConfig', 'FormFieldConfig'])) {
            $this->configSynchronizer->syncEntity($shortName);
        }
    }
}
```

### Paso 5: Servicios + Boletos

Ejecuta `Migrador::migrarServicio()`:
1. Fetch salidas desde legacy (TOP N)
2. Por cada salida:
   - Migrar empresa (si no existe)
   - Migrar trayecto (si no existe)
   - Crear o reusar recorrido
   - Migrar bus y asientos
   - Crear servicio
   - Migrar boletos de la salida (con venta, cliente, usuario)

## Control de memoria

```php
$this->resetDebugDataHolder();
```

Se resetea el `BacktraceDebugDataHolder` de Doctrine entre pasos para evitar agotamiento de memoria.

## Manejo de errores

Cada salida se migra en una transacción independiente:

```php
$this->newConn->beginTransaction();
try {
    // Migrar salida completa
    $this->newConn->commit();
} catch (\Throwable $e) {
    $this->newConn->rollBack();
    // Log error y continuar con la siguiente
}
```

## Resumen final

Al completar, el comando muestra una tabla con todas las entidades migradas y sus contadores.
