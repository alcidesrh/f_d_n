# ADR-039: Accesos con alcance — capacidades en el código, roles planos y asignaciones por empresa y estación

**Estado:** Aceptada (reemplaza ADR-003; modifica ADR-015 y la visibilidad por jerarquía de roles de ADR-018)

## Contexto

El dominio ya está casi completo; el siguiente paso es que cada persona haga solo lo suyo. El Flat Permission Set de ADR-003 no lo garantizaba:

- **La herencia de roles estaba al revés.** `PermissionManager` daba a cada rol los permisos de sus *ascendientes*, y `ROLE_USER` (que tienen todos) colgaba de `ROLE_ADMIN_ESTACION → ROLE_ADMIN_EMPRESA → ROLE_ADMIN`. Un usuario con solo `ROLE_USER` tenía 26 acciones, entre ellas `usuario.editar`, `iam.editar` y `config.editar`; con `usuario.editar` cambiaba la contraseña de cualquiera, incluido un superadmin.
- **La API genérica no tenía ninguna barrera.** 189 operaciones de GraphQL en 40 entidades y ~190 rutas REST de API Platform sin `security`: cualquier sesión podía darse `userRoles`, cambiar tarifas o borrar en lote cualquier tabla (`deleteAgnostic`).
- **Demasiados caminos para tener un permiso** (rol → permiso → acción, rol → acción, jerarquía, permiso y acción directos, acción denegada) y ninguno para decir **dónde**: el alcance por empresa o estación era una comprobación suelta en ~40 servicios, `Usuario.empresa` no la tenía nadie (el `TenantFilter` nunca se activaba) y `EntityVoter` armaba códigos (`usuario.delete`) que no existían en el catálogo.
- **Los roles del legado no daban nada:** 23 puestos (`ROLE_VENDEDOR_BOLETOS`, 251 usuarios) no tenían permisos del modelo nuevo; los vendedores no podían vender, pero sí editar usuarios.
- **Usuarios dados de baja en el legado entraban** (`enabled`, `locked` y `expired` no se migraban).

Los datos del legado dieron dos hechos: las estaciones son compartidas (12 de 38 venden para varias empresas, 24 de 28 agencias también) y un empleado trabaja para varias empresas (52 de 95 vendedores). Además, el legado ya guardaba el alcance de cada usuario (`custom_user_empresa`, `custom_user_estacion`).

## Decisión

Control de acceso por roles **con alcance** (el modelo de Azure RBAC o GCP IAM: una asignación es quién + qué rol + dónde). Lo configurable es **quién** hace qué y **dónde**; **qué es válido** (horas, estados, la estación donde sube el pasajero) son reglas del dominio y siguen en el código (`ReglasTransicion`, `ReglasEncomienda`, guards de los workflows).

### Las cuatro piezas

```
Capacidad  (código)  venta.vender, boleto.anular, salida.crear, bus.editar…
                     declara: por qué se acota (empresa, estación) y qué límites admite
Rol        (datos)   nombre + capacidades + límites        plano: no hereda
Asignación (datos)   usuario × rol × empresas × estaciones × vigencia (desde, hasta)
                     empresas / estaciones: conjunto o NULL = todas (también las futuras)
Regla      (código)  las de cada módulo
```

**Permitido ⇔ existe una asignación vigente cuyo rol tiene la capacidad y cuyo ámbito contiene la empresa y la estación de la operación.** Lo que puede un usuario es la unión de sus asignaciones: no hay negaciones ni excepciones por usuario; lo temporal se resuelve con la vigencia.

- **Catálogo** (`App\Acceso\Capacidad` + `Catalogo\CatalogoRecursos`): las del dominio en un enum, con nombre, área, dimensiones y límites; las del CRUD genérico salen del atributo `#[Recurso]` de cada entidad (`{snake}.ver|editar|eliminar`, o códigos del dominio compartidos como `boleto.ver`). Un rol solo guarda códigos del catálogo; `CapacidadVoter` solo responde por ellos (un código mal escrito no se abstiene en silencio: `CatalogoTest` comprueba que todo lo que el código exige existe).
- **Rol** (`Role`): `capacidades`, `limites`, `sistema` (todas, también las que se agreguen; no se edita) y `ROLE_USER` como rol base (lo tienen todos sin asignárselo; solo sirve para menús y administración, sin capacidades).
- **Asignación** (`Asignacion`): `empresas` y `estaciones` en JSON (NULL = todas), `desde`/`hasta` inclusive, `motivo`, `otorgadaPor`. A un usuario de agencia el ámbito no se elige: es su agencia con las empresas que vende (`Agencia.empresas`, ninguna = todas).
- **Ubicación** (`Ubicacion`, `Ubicador`): dónde cae una operación. Un registro sin empresa (compartido) solo lo cubre un alcance sobre todas; una operación sin estación, también. La estación es la de trabajo de quien opera o, para lo ya hecho, donde se vendió.

### Estación de trabajo

`Usuario.estacion` es dónde opera ahora; el usuario la cambia entre las de sus asignaciones (`PUT /api/me/estacion-trabajo`, `App\Acceso\EstacionTrabajo`). Si su alcance tiene una sola, queda fijada sola; si tiene varias, el frontend se la pide al entrar. La empresa no se elige: sale de la salida o de lo que se opera.

### Dónde se decide

- **Un punto de decisión**: `App\Acceso\Autorizacion` (carga las asignaciones vigentes una vez por petición y usuario; `ResetInterface`, sin caché entre peticiones del worker) y `CapacidadVoter` (`isGranted('capacidad', $sujeto)`; sujeto nulo = en algún ámbito; una `Ubicacion`; o un registro de la API genérica). Sin bypass de `ROLE_ADMIN`: para Symfony todos son `ROLE_USER`.
- **API genérica, denegar por defecto**: `Api\SeguridadPorDefecto` decora la metadata de API Platform (por dentro de su caché) y pone `security` a cada operación GraphQL y REST de `App\Entity` según su `#[Recurso]`: leer con `.ver` (o solo sesión si es de lectura libre), crear y modificar con `.editar` también sobre el objeto ya modificado (`securityPostDenormalize`: no se mueve un bus a una empresa ajena), borrar con `.eliminar`; lo de solo lectura (salidas, boletos, ventas, roles) no se escribe por ahí y lo oculto (`ApiToken`) no se publica. `Api\AlcanceEnColecciones` filtra los listados por las empresas de la capacidad de verlos. Los resolvers propios (`collectionAgnostic`, `deleteAgnostic`) comprueban la entidad y cada registro.
- **Piso de visibilidad**: `TenantFilter` filtra `Bus`, `Piloto`, `Salida`, `BoletoTarifa`, `EsquemaSalida` y `Establecimiento` por la unión de empresas de las asignaciones vigentes, más los registros compartidos (sin empresa); apagado con alcance sobre todas.
- **Servicios del dominio**: cada uno pide la capacidad sobre la ubicación de lo que opera (venta: empresa de la salida + estación de emisión o agencia; abordaje y encomiendas: empresa + estación de trabajo; salidas: empresa; ventas hechas: `AccesoVentas`, donde se vendió). Los listados y reportes usan el alcance como un OR de ámbitos (`Alcance::condicion`, `FiltroAlcance`), porque la unión de rectángulos no es un rectángulo.

### Límites

Conjunto cerrado declarado en el código (`Limite`), con valor por rol: `cortesias_por_dia` (asientos de cortesía que emite un usuario por día, en `RegistroVenta`) y `reasignaciones_por_dia` (boletos que reasigna por día, contados en la bitácora). Rige el más permisivo entre las asignaciones que dan la capacidad; sin valor, no hay límite. El cupo `reasignaciones` del legado no se migró: no se aplicaba por día (619 días sobre el cupo solo con cupo 3) y ningún boleto se reasignó dos veces.

### Administración delegada sin escalada (`App\Acceso\Delegacion`)

- Otorgar, modificar o revocar exige `iam.asignar` con un alcance que cubra el destino y **tener cada capacidad del rol en todo ese ámbito**. El rol de sistema solo lo otorga quien lo tiene. Nadie toca sus propias asignaciones.
- Sobre otro usuario (contraseña, datos, agencia, activarlo, borrarlo) solo actúa quien podría otorgarle cada una de sus asignaciones vigentes.
- Agregarle a un rol una capacidad (o aflojar un límite) exige tenerla en cada ámbito donde el rol ya está asignado. Quitar siempre se puede.
- Cada cambio queda en la bitácora (`usuario`: asignación otorgada, modificada, revocada, activado, desactivado, contraseña, estación de trabajo; `rol`: creado, modificado, eliminado) y avisa al afectado por su tópico privado de Mercure (`tipo: acceso`), que vuelve a pedir lo que puede.

### Usuario activo

`Usuario.activo`; `UsuarioActivo` (user checker del firewall) no deja entrar a un desactivado, ni con un token que ya tenía. Desactivar cierra sus sesiones.

### API

- `GET /api/me/acceso`: capacidades con sus ámbitos, lo que puede con cada entidad de la API genérica, si es de sistema, su estación de trabajo, las que puede elegir y una huella. El login ya no devuelve permisos.
- `/api/acceso` (`iam.ver` para leer): `catalogo`, `roles` (CRUD con `iam.roles`), `usuarios/{id}` (asignaciones, efectivo, qué puede cambiar quien mira), `usuarios/{id}/asignaciones` y `asignaciones/{id}` (con `iam.asignar`), `usuarios/{id}/activo`, `quien-puede?capacidad=&empresa=&estacion=` y `sin-alcance`.
- `GET /api/bitacora/{usuario|rol}/{id}` con `iam.ver`.

### Frontend

`core/acceso` (store de `/me/acceso`, reglas puras, API). El router no abre una pantalla sin su `meta.capacidad` (o, en el CRUD genérico, sin ver la entidad; crear exige editarla) y los menús, las cajas de `/administracion` y los accesos directos del tablero esconden lo que el usuario no puede abrir. La cabecera muestra la estación de trabajo y la cambia. Pantallas: `/configuracion/roles` (capacidades por área, matriz del CRUD, límites), `/configuracion/usuarios/:id/accesos` (asignaciones, efectivo, otorgar, revocar, activar, bitácora; acción «Accesos» del listado de usuarios) y `/configuracion/accesos` («¿Quién puede?» y «Sin alcance»). Lo que se esconde, la API lo niega igual.

### Menús y administración

Sin jerarquía de roles: un menú o una caja se ven si el usuario tiene asignado uno de sus roles, si lleva el rol base o si tiene el de sistema. Cada ítem, además, solo si puede abrir su pantalla.

### Migración

`Version20261022090000` convierte el modelo: los roles reciben sus capacidades (copia congelada de `App\Acceso\Legado\EquivalenciasLegado`; `ROLE_SUPER_ADMIN` es el de sistema y `ROLE_ADMIN` todo menos la migración), cada `user_role` (salvo `ROLE_USER`) pasa a una asignación con el alcance que se puede inferir (administradores y agencias, todo; los demás, las empresas para las que vendieron y su estación en los puestos de estación) y se borran `action`, `permiso`, sus uniones, la jerarquía, los permisos por usuario y las listas sin uso de `vue_route`. `app:acceso:alcance-legado` (el paso «iam» de la migración) rehace las asignaciones con el alcance real del legado y el estado activo; respeta las otorgadas a mano. En la base de desarrollo: 447 usuarios, 2.759 asignaciones, 245 desactivados (los que el legado ya no dejaba entrar) y 3 activos sin alcance, para revisar.

## Consecuencias

**Positivas:**

- Una sola manera de tener un permiso: cada «sí» lo explica una asignación (`origen()`, «¿Quién puede?»). Configurar un puesto nuevo no toca código; agregar una capacidad sí, a propósito.
- El alcance es de primera clase y cubre estaciones compartidas y empleados de varias empresas sin duplicar roles por lugar.
- Denegar por defecto en toda la API genérica: una entidad nueva sin `#[Recurso]` queda protegida con capacidades propias (y `CatalogoTest` pide declararla).
- Sin caché que invalidar: un cambio rige desde la petición siguiente y el navegador se entera por Mercure.

**Negativas / pendientes:**

- Las capacidades del CRUD genérico son muchas (85 en total); el editor las agrupa por área, pero un rol nuevo exige recorrerlas.
- Un registro compartido (sin empresa) solo lo modifica quien tiene alcance sobre todas; las tarifas del legado sin empresa son de ese tipo.
- Los reportes de encomiendas con alcance acotado son de una empresa y una estación a la vez (eligiendo entre las suyas); los de venta aceptan el conjunto.
- El tablero de gestión es por empresa completa: quien tiene `reporte.ventas` solo en algunas estaciones no lo ve (ve sus reportes).
- Un usuario de agencia sin estación cuenta como «de oficina» en el abordaje (como antes): `ROLE_AGENCIA_SALIDA` puede iniciar salidas de las empresas de su agencia en cualquier parada.
- `EquivalenciasLegado` es una decisión de negocio que hay que validar con quien opera cada puesto.
- `down()` de la migración es irreversible: volver al modelo anterior exige un respaldo.
