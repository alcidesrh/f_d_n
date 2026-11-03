# ADR-049: Navegación por capacidad — poda en el backend y audiencia opcional por rol

**Estado:** Aceptada (modifica la visibilidad de menús de ADR-018 y la de menús y administración de ADR-039; quita el rol base de ADR-039 y ADR-043)

## Contexto

Después de ADR-039, lo que un usuario puede hacer lo decide una sola cosa: sus asignaciones (rol × ámbito), consultadas como capacidades. Lo que *ve* en la navegación lo decidían dos políticas más:

- **Roles de audiencia en menús, cajas y enlaces de `/administracion`**, mantenidos a mano. En los datos repetían las capacidades y se desfasaban. El menú «Encomienda» enumeraba ocho roles: un rol nuevo con `encomienda.registrar` no lo veía hasta agregarlo a la lista. La caja «Usuarios y roles» solo la veía `ROLE_SUPER_ADMIN`, y un `ROLE_ADMIN` con `iam.ver` no la encontraba. El rol de sistema aparecía en 9 listas que ya veía sin estar en ellas.
- **La poda por capacidad en el navegador.** `podarMenu` y el filtro de enlaces de la administración resolvían cada destino con el router y lo comparaban con `meta.capacidad`. El backend mandaba todo: los nombres y paths de pantallas ajenas llegaban al cliente y la regla vivía solo en JavaScript. El «Agregar nuevo» de un enlace no se podaba: aparecía aunque el usuario no pudiera crear.
- **Excepciones por usuario.** Cajas y enlaces admitían usuarios sueltos, el tipo de excepción que ADR-039 eliminó del resto del modelo.
- **El rol base `ROLE_USER`.** No concedía nada: solo servía para decir «a todos» en esas listas.
- **Reglas con nombres fijos.** `puedeEntrar` reconocía `entity-list` y `entity-form` por nombre, y el selector de entidades de `ListPage` se mostraba según el nombre de rol `ROLE_SUPER_ADMIN`.

## Decisión

Lo que se ve sale de lo que se puede abrir. El backend lo decide con la misma regla que el router y, encima, una audiencia opcional por roles que solo puede acotar.

```
ítem/enlace visible ⇔ puede abrir su destino          (capacidad: obligatorio)
                    ∧ su audiencia lo incluye         (roles: opcional; vacía = todos; el de sistema, siempre)
menú/caja visible   ⇔ su audiencia lo incluye ∧ le queda ≥ 1 ítem/enlace visible
```

### Lo que exige cada pantalla, una sola vez

La ruta del frontend lo declara en `meta`:

- `capacidad`: un código o varios (alguno basta). Sin ella, la abre cualquiera con sesión.
- `recurso`: solo en las pantallas del CRUD genérico (`RecursoDeRuta`): `{ parametro, operacion: 'ver'|'editar', registro?, selector? }`. La entidad sale del parámetro (`bus-clase` → `BusClase`) y la pantalla exige `operacion` sobre ella. Si viene `registro` (`id`), se abre uno que ya existe y basta con verla. Sin la entidad es el selector, que exige `selector`.
  - `entity-list`: `{ parametro: 'entity', operacion: 'ver', selector: 'config.editar' }`
  - `entity-form`: `{ parametro: 'entity', operacion: 'editar', registro: 'id' }`

El frontend (`puedeEntrar`, guard del router) y el backend (`App\Navigation\Pantalla`) interpretan esos mismos datos, sin nombres de ruta fijos.

La sincronización de rutas (`POST /api/vue-routes/sync`, `config.editar`) los guarda en `VueRoute.capacidades` y `VueRoute.recurso`. Responde las capacidades que no están en el catálogo (`capacidadesDesconocidas`; esas pantallas no las abre nadie) y el navegador las avisa en consola. La dispara el shell al entrar quien tiene `config.editar`, ya con sesión. Antes la disparaba `main.ts` en el arranque, a menudo sin sesión, y fallaba en silencio. Un lote en el que ninguna ruta trae `capacidad` ni `recurso` viene de un navegador con la versión anterior (la PWA espera a que acepten la nueva): se actualizan nombres, paths y parámetros, pero se conserva lo que exige cada pantalla. Las sincronizaciones van de una en una (cerrojo de PostgreSQL). Antes, dos simultáneas creaban dos veces la misma ruta nueva, y solo una recibía lo que exige la pantalla. Si quedó alguna repetida bajo el mismo padre, la sincronización la junta con la más antigua: le pasa sus ítems de menú y sus hijas, y la borra.

### Poda en el backend

- `App\Navigation\Pantallas`: el catálogo de rutas sincronizadas (una consulta por petición, `ResetInterface`). Busca por nombre de ruta (con parámetros) o por path. `PatronRuta` es el comparador de paths de vue-router: `?`, `*`, `+` y restricciones, sin distinguir mayúsculas; los tramos fijos ganan a los parámetros y el comodín de «no encontrada» no se compara. Responde si el usuario puede abrir lo que hay ahí.
  - Lo que no conoce no se esconde: una ruta que no está en el catálogo, un path que no es de ninguna ruta (`/pagina`, servida por el backend) o una dirección externa. El frontend lo pinta inerte o lo abre, y su router decide.
- `UserMenus` (`GET /api/me/menus`) poda los ítems (con lo que cuelga de ellos), los grupos vacíos y los menús sin ítems.
- `PanelAdministracion::paraUsuario` (`GET /api/administracion`) poda los enlaces que llevan a lo que no puede abrir. El «Agregar nuevo» que no puede abrir sale como `null`, y las cajas sin enlaces no se mandan.
- `DefinicionRecurso::permite` es la regla única de «ver/editar/eliminar una entidad del CRUD genérico». La usan `/api/me/acceso` y `Pantalla`.

El frontend deja de podar: se borran `podarMenu`, `filtrarMenusCon` y el filtro de `AdministracionPage`. El guard del router sigue: es navegación, no presentación.

### Audiencia: curaduría, no seguridad

`App\Navigation\Audiencia::incluye` reemplaza a `MenuVisibility` y a `Administracion\Acceso`, y es una sola regla para menús, cajas y enlaces. Sin roles, a todos. Con roles, a quien tenga una asignación vigente de alguno. El rol de sistema, siempre. Sin jerarquía.

Sirve para no llenar la navegación de un puesto con pantallas que puede abrir pero no usa. Nunca amplía lo que se ve.

- Fuera los usuarios sueltos de cajas y enlaces (`seccion_administracion_usuario`, `enlace_administracion_usuario`).
- Un menú sin roles ya no es «de nadie»: para esconderlo, se quita de las áreas.
- El payload de la administración pasa de `acceso: { roles, usuarios }` a `audiencia: [id de rol]`.

### Sin rol base

Con «sin roles = todos», `ROLE_USER` no tiene uso y se borra de `role`. Para Symfony todos siguen siendo `ROLE_USER` (`Usuario::getRoles()`), como antes. Se quitan `Role::esBase()` y sus reglas: no se asigna, no lleva capacidades, no se asocia a listados. La migración del legado ya ignoraba ese rol (`EquivalenciasLegado::BASE`). En ADR-043, la configuración «para todos» sigue siendo la por defecto, la que no tiene roles.

### Migración (`Version20261103120000`)

Agrega las columnas de `vue_route` y deja la navegación como estaba:

- Saca de las áreas los menús sin roles (antes no los veía nadie).
- Deja sin roles lo que llevaba `ROLE_USER`.
- Quita el rol de sistema de las listas que tienen otros roles. Donde es el único, se queda: significa «solo el de sistema».
- Borra las tablas de usuarios sueltos (se detiene si tienen filas) y el rol base.

## Consecuencias

**Positivas:**

- Un rol nuevo con `encomienda.registrar` ve el ítem «Registrar» y su menú sin tocar la navegación, salvo que el menú tenga una audiencia que lo excluya a propósito.
- El backend no manda nombres ni paths de pantallas que el usuario no puede abrir. La regla es una y está en el servidor; el router la aplica igual con los mismos datos.
- El «Agregar nuevo» de la administración se esconde a quien no puede crear.
- Una capacidad mal escrita en `meta.capacidad` se avisa al sincronizar.
- Se borran una clase de reglas, un rol, dos tablas y la excepción por usuario.

**Negativas / pendientes:**

- El backend conoce las rutas por la sincronización. Tras cambiar una ruta, hasta que entra alguien con `config.editar`, rige la versión anterior: se puede ver algo que el router después no deja abrir, o al revés. No es un riesgo de seguridad, porque la API exige lo suyo.
- Las listas de lectura libre (rutas, íconos, ítems de menú, roles) las abre cualquiera. Sus enlaces en la caja «Sistema» se ven si la caja no tiene audiencia: es un caso para curar con roles.
- Las audiencias que hoy repiten capacidades (p. ej. los ocho roles de «Encomienda») se dejan como están: son curaduría y cambiarlas es decisión de negocio. Sobran cuando nombran todos los roles que pueden abrir algún ítem del menú.
- `/administracion`, `/ayuda`, `/chat` y `/autorizaciones` no exigen capacidad: su ítem lo ve cualquiera que vea su menú.
