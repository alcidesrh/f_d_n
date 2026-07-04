# Subdominios del Frontend

Para facilitar un mapa mental coherente, el código del frontend está categorizado en áreas de responsabilidad o "Subdominios lógicos".

## Mapeo Mental

Si necesitas localizar código, pregúntate primero ¿A qué proceso de negocio pertenece?

- **Ventas (`/sales`)**: Lógica de venta de boletos, asignación de asientos, anulación y reasignación de pasajes. Módulo que utiliza el empleado de estación.
- **Logística (`/logistics`)**: Gestión de autobuses, recorridos compartidos, asignación de pilotos y estado del "parking".
- **Comercial / CRM (`/crm`)**: Gestión de agencias, clientes frecuentes y empresas colaboradoras.
- **Configuración y Core (`/core`)**: Gestión de Estaciones, Destinos y parámetros base del sistema.
- **Seguridad / IAM (`/iam`)**: Gestión de cuentas de usuario, roles de estaciones, perfiles de permisos y flujos de login.
- **Componentes Base (`/components/ui`)**: Botones, tablas dinámicas, modales generados, componentes tipográficos y animaciones transversales.

## Gestión del Estado

Cada subdominio puede tener su propia tienda Pinia (`store`) si la información es requerida globalmente. Sin embargo, se prioriza el uso de Apollo Cache para la retención del estado de servidor, usando Pinia estrictamente para estado local del cliente (ej. preferencias de UI, carritos de venta temporales).
