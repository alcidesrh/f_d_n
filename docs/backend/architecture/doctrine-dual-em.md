# Doctrine Dual Entity Manager

## Arquitectura de dos Entity Managers

El backend utiliza dos Entity Managers de Doctrine para manejar dos bases de datos completamente diferentes:

| Entity Manager | Alias | Base de datos | Propósito |
|---------------|-------|---------------|-----------|
| `default` | EM principal | PostgreSQL 16 | Datos nuevos del sistema FDN |
| `systemfdn` | EM legacy | SQL Server 2012 | Lectura del sistema TerminalOmnibus heredado |

## Configuración

Archivo: `config/packages/doctrine.yaml`

### Default (PostgreSQL 16)

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                url: "%env(DATABASE_URL)%"
    orm:
        default_entity_manager: "default"
        entity_managers:
            default:
                connection: default
                naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
                auto_mapping: true
                mappings:
                    App:
                        type: attribute
                        dir: "%kernel.project_dir%/src/Entity"
                        prefix: 'App\Entity'
```

Características:
- Driver PDO PostgreSQL nativo
- Estrategia de naming: `underscore_number_aware` (respeta números en nombres)
- Mapping por atributos PHP 8
- DQL personalizado: función `CAST` para conversiones

### SystemFDN (SQL Server 2012)

```yaml
            systemfdn:
                connection: systemfdn
                naming_strategy: doctrine.orm.naming_strategy.underscore
                mappings:
                    systemfdn:
                        dir: "%kernel.project_dir%/src/EntitySistemaFdn"
                        prefix: 'App\EntitySistemaFdn'
```

Características:
- Driver personalizado `App\Doctrine\Driver\PDODblib\Driver`
- Estrategia de naming: `underscore` simple
- Sin auto-mapping (mapping explícito del directorio `EntitySistemaFdn/`)
- **Solo lectura**: este EM nunca persiste cambios

## Driver DBLib personalizado

Ubicación: `src/Doctrine/Driver/PDODblib/Driver.php`

Este driver extiende `Doctrine\DBAL\Driver\AbstractPostgreSQLDriver` pero se conecta a SQL Server 2012 usando PDO con la extensión `dblib`. Permite que Doctrine DBAL se comunique con el SQL Server legacy vía FreeTDS.

### Configuración de conexión

```yaml
            systemfdn:
                driver_class: App\Doctrine\Driver\PDODblib\Driver
                host: "%env(systemfdn_host)%"
                port: "%env(systemfdn_port)%"
                charset: UTF-8
                dbname: "%env(systemfdn_dbname)%"
                user: "%env(systemfdn_user)%"
                password: "%env(systemfdn_password)%"
```

Variables de entorno requeridas:
- `systemfdn_host` — host del SQL Server
- `systemfdn_port` — puerto (típicamente 1433)
- `systemfdn_dbname` — base de datos TerminalOmnibus
- `systemfdn_user` — usuario
- `systemfdn_password` — contraseña

### Conexión PDO directa

Para operaciones masivas, la migración usa un objeto `\PDO` directo configurado en `config/services.yaml`:

```yaml
PDO $oldPdo:
    class: \PDO
    arguments:
        $dsn: "dblib:host=%env(systemfdn_host)%:%env(systemfdn_port)%;dbname=%env(systemfdn_dbname)%"
        $username: "%env(systemfdn_user)%"
        $password: "%env(systemfdn_password)%"
```

Esto se inyecta via `#[Target('oldPdo')]` en los servicios de migración.

## Entidades legacy (112)

Ubicadas en `src/EntitySistemaFdn/`, incluyen:

- Catálogos: Empresa, Estacion, Cliente, MarcaBus, TipoBus, ClaseAsiento
- Operacionales: Salida, Boleto, Itinerario, Ruta, Bus, Piloto
- Financieras: Factura, TarifaBoleto, TarifaEncomienda, Caja, CorteVenta
- Seguridad: User, Rol
- Soporte: Log, Job, Notificacion, PluginImpresion

Estas entidades se usan exclusivamente en el pipeline de migración para leer datos legacy y transformarlos al nuevo modelo.

## Limitaciones

1. **No hay joins entre EMs**: No se pueden hacer relaciones Doctrine entre entidades de distintos entity managers.
2. **Solo lectura legacy**: El EM `systemfdn` es estrictamente de lectura.
3. **Driver DBLib no oficial**: El driver personalizado puede tener limitaciones con tipos de datos modernos de SQL Server.
4. **Transacciones separadas**: Cada EM maneja sus propias transacciones independientemente.
5. **Sin auto-mapping legacy**: Las entidades del SQL Server deben mapearse manualmente.
