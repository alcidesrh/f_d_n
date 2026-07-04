# Arquitectura del Backend

El backend es el core del sistema de transporte, construido bajo **Symfony 8** y expuesto principalmente mediante **API Platform**.

## Puntos Clave del Diseño

- **Multi-tenant Lógico**: La separación de datos por empresa (autobuses, recorridos) se maneja a nivel de aplicación (generalmente por claves foráneas o filtros de API Platform) más que por esquemas de base de datos separados.
- **Doble Persistencia (Entity Managers)**: 
  - `default`: La base de datos principal moderna (PostgreSQL).
  - `legacy`: Conexión hacia SQL Server (TerminalOmnibus) usada exclusivamente para migraciones y operaciones de lectura heredadas.
- **GraphQL First**: Aunque API Platform expone REST de forma automática, el sistema prioriza GraphQL para soportar el CRUD dinámico del frontend a través del schema tipado.
- **Servidor FrankenPHP**: Permite tiempos de respuesta ultrarrápidos utilizando el modo "worker" para mantener el kernel de Symfony en memoria.

[Ver estructura de directorios](./directories.md)
