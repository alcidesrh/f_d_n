# Migración desde Legacy

Una parte sustancial del backend actual es la orquestación de la migración de datos desde el antiguo sistema "TerminalOmnibus" hacia el nuevo sistema.

## Origen de Datos

- **Base de Datos**: Microsoft SQL Server 2012.
- **Conexión**: Vía un EntityManager secundario (`legacy`) configurado en Doctrine usando los drivers ODBC o pdo_sqlsrv a través de un contenedor Docker adaptado (`sqlserver_php`).

## Comandos de Consola (`Makefile`)

El proceso es intenso y puede tomar varias horas para tablas grandes (como Boletos o Historiales). Se maneja mediante comandos de consola de Symfony (`app:migrar`, `app:migrar:estaticos`, `app:migrar:todo`).

- `make migrar-entities`: Permite sincronizar entidades estáticas (Empresas, Estaciones, Buses).
- `make migrar-todo`: Ejecuta el pipeline completo.
- `make sincronizar`: Comando diseñado para traer sólo datos incrementales de la base legacy sin reconstruir la base completa.

## Estrategia de Migración

1. **Lectura Inmutable**: El sistema lee desde SQL Server, instanciando DTOs u Objetos directamente. No se escriben datos hacia la base legacy a menos que sea una flag estricta de "migrado".
2. **Transformación**: Mapeo de columnas viejas a estructuras relacionales modernas.
3. **Carga (Upsert)**: Se utiliza el EntityManager principal para persistir las entidades.
