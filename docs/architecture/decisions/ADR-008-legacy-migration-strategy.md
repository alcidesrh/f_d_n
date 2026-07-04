# ADR-008: Estrategia de migración desde TerminalOmnibus

**Estado:** Aceptada

## Contexto

TerminalOmnibus es el sistema legado que FDN Transportes reemplaza. Consta de una base SQL Server 2012 con más de 110 tablas, un frontend PHP (posiblemente Symfony 2.2) y procesos ETL propietarios. La migración debe trasladar datos históricos y operativos al nuevo sistema sin interrumpir las operaciones diarias de la empresa.

Los datos incluyen información crítica: boletos emitidos, ventas, facturación, clientes, rutas, horarios, pilotos, buses, personal, roles y permisos. El volumen total de datos se estima en miles de registros solo de boletos (6679 salidas documentadas).

## Decisión

Se implementa una **estrategia de migración incremental por fases**:

1. **Fase 0 — Entidades estáticas**: empresas, localidades, estaciones, marcas de bus, clases de asiento, tipos de documento, roles base, permisos. Migradas mediante `app:migrar:estaticos`.
2. **Fase 1 — Configuración y seguridad**: roles, permisos, acciones, usuarios con contraseñas migradas usando el `LegacySha512PasswordHasher` para mantener compatibilidad con hashes SHA-512 del sistema anterior.
3. **Fase 2 — Entidades transaccionales**: clientes, pilotos, buses, asientos, trayectos, recorridos, tarifas, boletos históricos. Migradas mediante `app:migrar:todo`.
4. **Fase 3 — Sincronización continua**: script `app:sincronizar` que corre periódicamente para traer datos nuevos desde SQL Server.
5. **Fase 4 — Corte definitivo**: cuando la migración se complete, se desconecta SQL Server y el sistema opera completamente sobre PostgreSQL.

Cada comando de migración acepta parámetros para controlar el lote (`--boletos=500`), limpiar datos previos (`--clean`) y saltar fases completadas (`--skip-estaticos`, `--skip-iam`, `--skip-config`). Las entidades migradas mantienen su `legacyId` para trazabilidad.

## Consecuencias

**Positivas:**

- Migración sin interrupción del servicio: el sistema legado sigue funcionando durante la transición
- La migración es incremental y repetible: cada ejecución retoma desde donde quedó
- El `LegacySha512PasswordHasher` permite a usuarios existentes iniciar sesión sin cambiar contraseña
- La sincronización continua minimiza la ventana de datos no migrados
- El comando `make migrar-todo` orquesta todo el proceso

**Negativas:**

- La migración completa toma horas (6679 salidas de boletos procesadas)
- La consistencia de datos entre ambos sistemas es eventual hasta el corte definitivo
- El proceso de migración es complejo de configurar (requiere conexión a SQL Server, variables de entorno específicas)
- Las contraseñas SHA-512 legadas deben migrarse progresivamente al algoritmo nativo de Symfony
- Datos huérfanos o inconsistentes en el sistema legado pueden causar fallos en la migración
