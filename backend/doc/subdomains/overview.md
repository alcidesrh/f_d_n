# Subdominios del Backend

Alineado con el Frontend, el Backend se divide lógicamente (y a menudo por namespaces dentro de `src/`) en áreas de negocio concretas (Subdominios o "Bounded Contexts").

## Estructura Lógica

- **Ventas (`Sales`)**: Entidades como `Boleto`, `Factura`, `Asiento`. Maneja la lógica transaccional, bloqueos de asientos por concurrencia y anulaciones.
- **Logística (`Logistics`)**: Entidades como `Bus`, `Trayecto`, `Empresa`, `Piloto`. Responsable de la planificación de horarios y alternancia de viajes entre empresas asociadas.
- **Infraestructura Core (`Core`)**: Entidades geográficas o estructurales inmutables como `Estacion`, `Destino`, `Marca`.
- **Identidad (`IAM`)**: Manejo de `Usuario`, `Rol`, perfiles de acceso y seguridad.
- **Integración (`Integration / Legacy`)**: Clases y comandos dedicados exclusivamente a leer la base de datos de TerminalOmnibus y transformar datos (Capa Anti-corrupción).

Esta separación permite que a nivel de backend se puedan definir "Handlers" o "Managers" específicos que encapsulan la lógica compleja antes de exponerla vía API Platform.
