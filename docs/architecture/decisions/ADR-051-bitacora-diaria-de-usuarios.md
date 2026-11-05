# ADR-051: Bitácora diaria de los usuarios

**Estado:** Aceptada

## Contexto

La bitácora de negocio (ADR-028, `bitacora`) guarda qué le pasó a una salida, un boleto, una encomienda o a los accesos, pero no responde «¿qué hizo este usuario hoy?»: no ve los inicios y cierres de sesión, las consultas, las pantallas que abrió, los errores que tuvo ni los cambios a las demás entidades (buses, rutas, tarifas…). La administración pidió un registro diario por usuario lo más detallado posible, configurable desde la administración (retención de 2 meses por defecto) y selectivo: seguir a todos los usuarios o solo a algunos.

Volumen de referencia (logs del legado, 23–29 jul 2026): unas 19 000 peticiones dinámicas y 390 inicios de sesión por día; se supone ×3 con la SPA nueva. Medido en PostgreSQL 16 local: 0,39 ms por fila con commit propio, 0,14 ms dentro de una transacción y 0,04 ms por fila en lotes de 100; ~550 B por fila con índices.

## Decisión

### Dos registros, unidos por la petición

- **`actividad`** (nuevo): cada petición a la API, sesión y acción de pantalla de un usuario. Solo DBAL (sin entidad), fuera del diff de migraciones (`schema_filter`).
- **`bitacora`** (ADR-028, se amplía): sigue siendo la bitácora de negocio y **siempre está encendida para todos** (`ConteoDiario` y la auditoría dependen de ella). Gana `request_id` y el índice `(usuario_id, fecha)`; con `cambiosEntidades`, `Bitacora\RegistradorCambios` anota además los cambios campo a campo (`entidad = 'cambio'`, `{clase, id, etiqueta, cambios: {campo: {de, a}}}`) de toda entidad de `App\Entity` que `TipoRegistro` no cubre, salvo las de `RegistradorCambios::EXCLUIDAS` (tokens, chat, correo, GPS, tareas…). Los campos con nombre de secreto quedan `***`.

`CapturaPeticion` fija un `request_id` (UUID; respeta un `X-Request-Id` válido que mande el cliente) al empezar cada petición y lo devuelve en la cabecera `X-Request-Id`. Las filas de `actividad` y de `bitacora` de esa petición lo llevan: la consulta junta cada cambio de negocio con la petición que lo hizo.

### Captura sin costo en la respuesta

`Actividad\RegistroActividad` (servicio con `ResetInterface`) acumula las filas en memoria y `CapturaPeticion` las escribe en `kernel.terminate`, ya enviada la respuesta, en **un solo `INSERT` de varias filas**. Un fallo de la bitácora queda en el log y nunca rompe la petición.

- **Peticiones:** método, ruta normalizada (`RutaNormalizada`: ids y UUID → `{id}`, entidad y registro), estado, duración, IP, agente, operación GraphQL (`OperacionGraphql`: `query Salidas`, `mutation createBus`; una `query` cuenta como lectura). El detalle (`Detalle`) guarda los parámetros de consulta y, en nivel máximo, el cuerpo JSON, **solo los campos de una lista blanca** (`Politica::CAMPOS_BASE` + `camposExtra`); contraseñas, tokens, tarjetas, cookies… quedan `***`. El detalle se acota a `maxDetalleKb`; más grande, se guarda solo qué claves tenía.
- **Sondeos:** la SPA marca sus peticiones de fondo (`silent` en `core/http.ts`) con `X-Actividad: sondeo`. No dejan una fila cada una: se cuentan por usuario y ruta (`accion = 'consultas'`, `detalle.cantidad`). En modo worker de FrankenPHP el acumulador vive entre peticiones y se vuelca cada 60 s (una fila por usuario, ruta y minuto); fuera del modo worker no hay memoria entre peticiones y cada sondeo es su propia fila con cantidad 1.
- **Sesiones:** `CapturaSesion` anota inicio (`/api/login`), cierre (`LogoutEvent`) e intentos fallidos (con el usuario intentado y el motivo; sin `usuario_id`).
- **Pantallas** (nivel máximo): `core/actividad` en la SPA anota al navegar (con cuánto duró la pantalla anterior), al imprimir y al tocar un elemento con `data-actividad="<acción>"`, y manda el lote a `POST /api/actividad` cada `intervaloPantallaSegundos`, al ocultar la pestaña (`keepalive`) y antes del logout. Solo lo hace si `GET /api/actividad/ajustes` dice que a ese usuario se le sigue. El backend acepta hasta 200 eventos, valida la hora del navegador (últimas 24 h) y quita secretos.

`/api/actividad` nunca deja fila de petición (su contenido va como pantallas).

### Configuración en la base (`configuracion_bitacora`, una fila)

Como ADR-047: entidad `ConfiguracionBitacora`, leída por `AjustesBitacora` (DBAL, se puede leer dentro de un `flush`; copia por proceso de 30 s, que `GestionBitacora` olvida al guardar) y editada desde la administración (`/configuracion/bitacora-diaria`, capacidad `bitacora.configurar`):

| Ajuste | Por defecto | Qué hace |
|---|---|---|
| `activa` | sí | Apagada, solo queda la bitácora de negocio. |
| `nivel` | medio | **mínimo:** sesiones y escrituras. **medio:** además lecturas y sondeos. **máximo:** además pantallas y cuerpos de las peticiones. |
| `familiasApagadas` | — | Apaga una familia (sesión, petición, pantalla, sondeo) que el nivel incluye. |
| `alcance` + `seleccion` | todos | `seleccionados`: solo los usuarios que entran por alguna entrada `{tipo: usuario\|rol\|empresa\|estacion, id, desde?, hasta?}` (fechas inclusivas, hora de Guatemala). Rol y empresa salen de las asignaciones vigentes (`Autorizacion`, ya calculadas en la petición); estación, de la estación de trabajo. Con alcance seleccionados y la bitácora activa, la selección no puede quedar vacía. |
| `retencionMeses` | 2 | Meses de detalle en `actividad` (1–60). |
| `exportarAntesDeBorrar` | sí | Antes de borrar un mes, CSV comprimido en `BITACORA_EXPORTACION_DIR` (`/data/bitacora` en Docker). |
| `retencionResumenMeses` | 0 | Meses del resumen diario (0 = sin límite). |
| `horaResumen` | 00:15 | Desde esa hora se resume el día anterior. |
| `intervaloPantallaSegundos` | 30 | Cada cuánto manda la SPA sus pantallas (10–600). |
| `maxDetalleKb` | 4 | Tamaño máximo del detalle de una fila (1–64). |
| `camposExtra` | — | Campos del cuerpo que se suman a la lista blanca. |
| `cambiosEntidades` | sí | Cambios campo a campo de todas las entidades en `bitacora`. |

`Actividad\Politica` es la foto inmutable de la configuración que decide qué se registra (`registra`, `sigue`, `tipoDePeticion`); es pura y tiene sus pruebas.

### Tabla particionada por mes y resumen diario

`actividad` está particionada por rango de `fecha` (`actividad_AAAA_MM`, en hora de Guatemala) más `actividad_otros` por defecto. La clave es `(id, fecha)`; índices `(usuario_id, fecha)` y BRIN sobre `fecha`. Borrar un mes viejo es un `DROP` de su partición, sin `DELETE` ni `VACUUM`.

La tarea `bitacora_diaria` del programador (ADR-044, cada hora) en `Actividad\Tarea\MantenerBitacoraHandler`:
1. **`Particiones::asegurar`**: crea las particiones de este mes y los dos siguientes; si `actividad_otros` tiene filas de ese mes, las mueve antes de adjuntar.
2. **`ResumenDiario::guardar`**: pasada `horaResumen`, si falta el resumen de ayer lo escribe en `actividad_resumen_diario` (una fila por usuario y día: primera y última actividad, minutos activos —minutos distintos con actividad que no es sondeo—, sesiones, peticiones, escrituras, errores, sondeos, pantallas, cambios y `operaciones` de negocio por `entidad.operacion`). Es idempotente (`ON CONFLICT`).
3. **`Particiones::purgar`**: exporta (si se pide) y borra los meses más viejos que `retencionMeses`; purga el resumen según `retencionResumenMeses`.

### Consulta

`/auditoria/bitacora-diaria` (capacidad `bitacora.usuarios`) con `ConsultaBitacoraDiaria`:
- `GET /api/bitacora-diaria/dia?fecha=`: quiénes tuvieron actividad y su resumen (el guardado para un día cerrado; si no, calculado al vuelo).
- `GET /api/bitacora-diaria/dia/{usuario}?fecha=`: su resumen y la línea de tiempo que mezcla `actividad` y `bitacora` (hasta 5000 entradas; `cortada` si había más). La pantalla agrupa cada cambio de negocio bajo su petición y filtra por familia, errores y consultas.

## Consecuencias

- **Rendimiento:** la respuesta no espera a la bitácora (se escribe en `terminate`); el costo es ~0,04 ms por fila en el lote más leer la configuración (cacheada 30 s). Con la selección por rol o empresa se usan las asignaciones que la petición ya cargó. Almacenamiento estimado: ~2 GB/año en nivel mínimo, ~12 GB/año en medio y ~66 GB/año en máximo para todos los usuarios; con la retención de 2 meses queda en una fracción de eso, y el resumen diario ocupa poco (una fila por usuario y día).
- La bitácora de negocio sigue en la misma transacción que el cambio (ADR-028); juntarla en un solo `INSERT` en `postFlush` la sacaría de la transacción, así que no se hace.
- Un intento fallido de inicio de sesión no tiene `usuario_id`: no aparece en la línea de tiempo de nadie (sí en la tabla).
- Una partición que falte (la tarea no corrió en meses) no rompe nada: las filas caen en `actividad_otros` y se mueven cuando se crea.
- `actividad` y sus particiones se manejan con SQL: `doctrine:schema:validate` y `make:migration` no las ven (`schema_filter: ~^(?!sync_|actividad)~`).
