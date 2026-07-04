# Identidad y Accesos (IAM)

El control de acceso es crítico debido a las múltiples estaciones y roles en el entorno empresarial.

## Roles y Usuarios

- **Usuario**: Representa a un empleado o administrador. Todo usuario tiene al menos un nivel base.
- **Roles / Perfiles**: Determinan las acciones permitidas (Ej. `ROLE_VENTAS`, `ROLE_SUPERVISOR`, `ROLE_ADMIN`).
- **Asignación de Estación**: Muchos usuarios (ej. vendedores) están atados a una o múltiples *Estaciones*. Esto limita los datos que pueden ver o modificar (sólo viajes de su origen, etc.).

## Flat Permission Set y Voters

En lugar de utilizar jerarquías complejas en la base de datos, el sistema se inclina hacia un "Flat Permission Set" en conjunción con **Symfony Voters**:

1. **Permisos Planos**: Atributos granulares (ej. `TICKET_CANCEL`, `TICKET_SELL`).
2. **PermissionManager**: Interfaz que abstrae la asignación de permisos según el perfil del usuario.
3. **Voters (Symfony)**: Clases en Symfony que deciden en tiempo real si el usuario actual tiene acceso sobre un recurso específico. 
   - *Ejemplo*: El `TicketVoter` valida no solo si el usuario tiene `TICKET_CANCEL`, sino si el boleto fue vendido en su propia estación.

## Configuración y Seguridad

La autenticación primaria se realiza mediante tokens (JWT) manejados por LexikJWTAuthenticationBundle, permitiendo una comunicación stateless segura desde el SPA del frontend.
