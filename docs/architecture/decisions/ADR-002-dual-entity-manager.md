# ADR-002: Dual Entity Manager (PostgreSQL + SQL Server legacy)

**Estado:** Aceptada

## Contexto

El sistema FDN Transportes reemplaza un sistema legado llamado TerminalOmnibus, construido sobre SQL Server 2012 con más de 100 tablas. La migración no puede hacerse de forma instantánea: los datos históricos (boletos, ventas, facturación) deben permanecer accesibles durante el período de transición. Además, ciertos procesos operativos dependen de consultas a datos históricos que aún no han sido migrados.

Se requiere una arquitectura que permita convivir ambos motores de base de datos: PostgreSQL 16 como almacenamiento principal del nuevo sistema, y SQL Server 2012 como fuente de datos legada en modo solo lectura.

## Decisión

Se implementan **dos Entity Managers en Doctrine**: `default` (PostgreSQL 16) y `systemfdn` (SQL Server 2012 vía PDO DBLib y driver MS ODBC). Las entidades del dominio activo (`src/Entity/`) se mapean al EM `default`, mientras que las entidades legadas (`src/EntitySistemaFdn/`) —más de 110 clases— se mapean al EM `systemfdn`.

La conexión `systemfdn` usa el driver `PDODblib\Driver` personalizado para compatibilidad con SQL Server 2012 a través de FreeTDS y el driver `sqlsrv` de Microsoft para Debian 13. El entity manager legado se declara como conexión de solo lectura en los casos de uso de migración.

## Consecuencias

**Positivas:**

- Datos históricos accesibles durante todo el proceso de migración
- Migración por lotes: las entidades se migran de forma incremental desde SQL Server a PostgreSQL
- Los comandos de migración (`app:migrar:todo`, `app:migrar:estaticos`) orquestan la transferencia entre EMs
- La entidad `Boleto` de PostgreSQL mantiene un campo `legacyId` para trazabilidad
- Arquitectura probada con más de 110 entidades legadas mapeadas

**Negativas:**

- Complejidad operativa: dos conexiones de base de datos, dos pools de conexiones
- Las transacciones no pueden abarcar ambos EMs; la migración debe manejar consistencia eventual
- El driver de SQL Server en FrankenPHP/Debian 13 requiere librerías MS ODBC adicionales en la imagen Docker
- La herramienta de migración es lenta (6679 salidas, horas de procesamiento)
- Mayor consumo de memoria en el contenedor backend por la doble conexión
