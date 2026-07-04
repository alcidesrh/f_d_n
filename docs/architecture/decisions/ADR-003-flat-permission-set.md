# ADR-003: Flat Permission Set vs RBAC tradicional

**Estado:** Aceptada

## Contexto

El sistema FDN Transportes requiere un modelo de autorización flexible. Los usuarios tienen roles (superadmin, admin, operador, consulta) pero también necesitan permisos específicos que cruzan los límites de los roles tradicionales. Por ejemplo, un usuario con rol "operador" puede necesitar acceso puntual a una acción administrativa sin cambiar su rol base.

El modelo RBAC puro (Role-Based Access Control) asigna permisos exclusivamente a través de roles, lo que dificulta los casos donde un usuario necesita permisos adicionales específicos sin heredar todo un rol superior. Tampoco permite denegar acciones específicas a usuarios dentro de un rol.

## Decisión

Se implementa un modelo **Flat Permission Set** con las siguientes características:

1. **Roles** con jerarquía: `ROLE_SUPER_ADMIN → ROLE_ADMIN → ROLE_OPERADOR → ROLE_CONSULTA → ROLE_USER`. Cada rol tiene un conjunto de permisos.
2. **Permisos**: agrupaciones de acciones (ej: "Gestión de Boletos" contiene `boleto.crear`, `boleto.editar`, `boleto.anular`).
3. **Acciones directas**: usuarios pueden tener acciones explícitamente concedidas además de las de su rol.
4. **Acciones denegadas**: acciones explícitamente denegadas a un usuario, que eliminan permisos heredados del rol.

El `PermissionManager` (en `src/Security/PermissionManager.php`) calcula el conjunto efectivo de acciones para cada usuario: `roles → permisos de roles → acciones de permisos + acciones directas - acciones denegadas`. El resultado se cachea por `userId`.

Dos Voters de Symfony (`ActionVoter`, `EntityVoter`) evalúan el permiso en cada operación.

## Consecuencias

**Positivas:**

- Flexibilidad granular: cada usuario puede tener exactamente los permisos que necesita
- Las acciones denegadas permiten restringir usuarios sin modificar roles compartidos
- El cacheo por usuario evita recalcular en cada petición
- Compatible con el sistema de Voters de Symfony y la jerarquía de roles nativa
- Los fixtures de prueba demuestran perfiles variados (superadmin 31 acciones, consulta 5 acciones)

**Negativas:**

- Mayor complejidad en la UI de administración de permisos (selección de acciones individuales)
- El cálculo de permisos efectivos requiere recorrer la jerarquía de roles recursivamente
- La invalidación de caché debe ocurrir en cada cambio de permisos o roles del usuario
- Riesgo de configuraciones inconsistentes si se conceden y deniegan las mismas acciones
