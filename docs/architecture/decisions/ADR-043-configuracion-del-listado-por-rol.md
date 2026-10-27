# ADR-043: Configuración del listado por rol

**Estado:** Aceptada

## Contexto

El listado genérico (`ListPage`, ADR-029) se configuraba una vez por entidad: `EntityConfiguration` tenía sus columnas (`CollectionFieldConfig`) y sus opciones (`listOptions`). Distintos puestos necesitan ver el mismo listado de distinta manera: otras columnas, otro orden y, sobre todo, sin los botones que no usan (Seleccionar, Nuevo, Editar, Eliminar).

## Decisión

- **`ListConfiguration`** entre la entidad y sus columnas: `EntityConfiguration` (una por entidad: formulario e ícono) → una o más `ListConfiguration` (nombre, prioridad `position`, roles, `listOptions`) → sus `CollectionFieldConfig`. Los campos del formulario siguen siendo de la entidad.
- **Por defecto**: la configuración sin roles. Hay exactamente una por entidad (la crea la sincronización; la migración convirtió la de siempre en la por defecto). No se elimina ni se le asocian roles.
- **Un rol, una configuración por entidad.** Una configuración puede tener varios roles. El rol base (`ROLE_USER`) no se asocia: para todos está la por defecto. Reglas puras en `App\ConfiguracionListado\Reglas`; toda escritura bloquea la `EntityConfiguration` de la entidad (`SELECT … FOR UPDATE`) y las comprueba sobre todas sus configuraciones. No hay restricción en la base: la tabla intermedia `list_configuration_role` no conoce la entidad.
- **Qué le toca a un usuario**: la primera configuración (menor `position`) con alguno de sus roles con asignación vigente (ADR-039); si ninguna, la por defecto. Con roles en varias configuraciones de la misma entidad, gana la prioridad.
- **Botones**: `listOptions` suma `create` (Nuevo), `edit` y `delete` (íconos de la fila) a `selectable` (Seleccionar). Ocultarlos no quita el permiso: la API sigue decidiendo, y encenderlos no da lo que la capacidad o la entidad no permiten (`resolveListFeatures`).
- **API**: `GET /api/me/configuracion/{entidad}` (cualquier usuario con sesión) entrega los campos del formulario y la configuración del listado que le toca (`listConfig: { id, nombre }`). El editor usa `/api/configuracion-listados/{entidad}` (`config.editar`: listar, crear como copia, guardar nombre/roles/opciones/columnas, eliminar). `CollectionFieldConfig` deja de ser recurso de API Platform; la mutación `updateWithRelationsEntityConfiguration` solo guarda el formulario.
- **Metadata (ADR-036)**: la huella de la configuración de una entidad baja por las hijas `ConfiguracionDelCliente` a cualquier profundidad y suma los ids de sus relaciones a muchos (los roles): cambiar cualquier configuración del listado o sus roles publica una revisión de la entidad. El navegador vuelve a pedirla también cuando cambian los accesos del usuario (`configuracionesVencidas`), y si le toca otra configuración arma la vista de nuevo (no mezcla la vista guardada de la anterior).

## Consecuencias

- Las columnas nuevas de una entidad se agregan a todas sus configuraciones del listado al sincronizar.
- El editor (`/configuracion/entidades/:entidad`) edita una configuración del listado a la vez; crear, renombrar, cambiar roles y eliminar se guardan en el acto, columnas y opciones con "Guardar cambios".
- La prioridad entre configuraciones es el orden de creación; no hay todavía una forma de reordenarlas en el editor.
