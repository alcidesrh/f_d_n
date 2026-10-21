# ADR-036: Metadata del cliente por revisiones: el navegador se pone al día solo (automático) o cuando un administrador publica (manual)

**Estado:** Aceptada

## Contexto

El frontend guarda en el navegador dos clases de metadata (ADR-004, ADR-010):

- **El schema GraphQL** (clave global `schema`): la introspección aplanada por entidad con la que arma listados, formularios y consultas.
- **La configuración de cada entidad** (clave `usuario/{usuario}/entity:{Entidad}`): columnas y campos de `EntityConfiguration`, junto con el estado del usuario (filtros, orden, vista) y datos en caché (`items`, `fullList`).

Una vez guardadas se reusaban sin preguntar. El schema solo se renovaba al subir `SCHEMA_VERSION` (pensada para cambios de formato) o al borrar el almacenamiento: un despliegue que agregaba, quitaba o renombraba un campo no llegaba a un navegador que ya lo tenía, y sus consultas fallaban con «Cannot query field…». La configuración se pedía una vez por entidad en cada carga de página (y, en desarrollo, en cada `init()`: el devtools de Pinia pasa un `Proxy` distinto como `this` y el `WeakSet` que debía evitarlo no acertaba nunca), así que una pestaña abierta no se enteraba de un cambio. En el backend había media solución sin usar: `ConfigChangePublisher` publicaba en Mercure tópicos que nadie escuchaba, `GET /api/config-versions` no lo leía nadie y `updatedAt` no servía como versión (la sincronización cambia filas hijas sin tocarlo).

Se pidieron dos maneras de poner al día el navegador: **automática** (al cambiar algo en el backend) y **manual** (un administrador consciente de los cambios lo dispara), las dos actualizando solo lo que cambió y sin código dedicado a entidades o campos.

## Decisión

Las dos maneras son el mismo mecanismo con distinto disparador: una **revisión** publicada que los navegadores comparan con la suya.

### Backend (`App\Metadata`)

- **Revisión** (`revision_metadata`, entidad `RevisionMetadata`, solo se agrega; el id es el número): el `estado` publicado —huella del schema y de la configuración de cada entidad, cada una con la revisión en que cambió por última vez, la última revisión que pidió recargar todo y la última con aviso—, lo que cambió (`cambios`), el origen (`automatica`, `manual`, `despliegue`), quién y la nota. `RegistroRevisiones` la crea con DBAL en su propia transacción, una a la vez entre procesos (`pg_advisory_xact_lock`), y solo si algo cambió (o se pidió aviso o recargar todo).
- **Huellas, sin código por entidad** (`HuellasVigentes`, `Huellas`): el schema es el sha256 del SDL (`SchemaPrinter`, tipos ordenados), guardado por compilación del contenedor en el pool `cache.metadata` (adaptador de sistema, versionado con cada compilación también en dev: armar el schema cuesta más de un segundo). La configuración se lee con DBAL de lo confirmado: cada columna de `entity_configuration` (menos su id y `updatedAt`) y de sus hijas que implementan `ConfiguracionDelCliente`, en su orden. Una columna nueva entra sola.
- **Manifiesto** `GET /api/metadata` (cualquier usuario con sesión): `{ revision, esquema: { revision, huella }, configs: { Entidad: revision }, completa, aviso }`, sin las huellas de la configuración. La etiqueta (`ETag`) es la revisión: el navegador revalida y recibe 304. La primera lectura crea la primera revisión con todo lo vigente, y si el schema cambió desde la última (código desplegado sin el comando) publica antes el schema.
- **Avisos**: tras confirmar, Mercure en el tópico público `/metadata` solo con `{ revision }` (perder uno no importa: cuenta la última). Y la cabecera `X-Metadata-Revision` en cada respuesta de `/api/*` y `/graphql` a un usuario con sesión (recordada 2 s por proceso; expuesta en CORS): un navegador sin Mercure se entera en su próxima petición.
- **Despliegue**: `app:metadata:revisar --sincronizar` (lo corre el entrypoint del contenedor `backend`, no el `worker`) sincroniza la configuración con el mapeo de Doctrine y publica una sola revisión si cambió el schema o (modo automático) la configuración. Sin cambios no publica nada; si falla, el contenedor arranca igual.
- **Modo** (`METADATA_PUBLICACION`, por defecto `automatica`):
  - *automática*: `VigilanteConfiguracion` (listener `onFlush`) anota de qué entidades se guardó configuración —cualquier `ConfiguracionDelCliente`: editor, GraphQL, REST, sincronización, migración— y `PublicarAlTerminar` publica una sola revisión al terminar la petición, el comando o el mensaje, a nombre del usuario. Una sincronización de 60 entidades da una revisión, no 60.
  - *manual*: lo guardado espera. `GET /api/metadata/publicacion` (`config.editar`) compara lo vigente con lo publicado (pendiente) y lista las últimas revisiones; `POST` publica todo lo vigente con nota opcional (500), aviso («Recargar» en cada navegador) y «recargar todo» (cada navegador vuelve a pedir schema, configuración y listas guardadas aunque no se vean cambios). Sin nada que publicar responde 409.
  - **El schema se publica solo en ambos modos**: un schema viejo rompe las consultas.
- Se eliminan `ConfigChangePublisher`, el recurso `ConfigVersions` (`/api/config-versions` y sus operaciones GraphQL) y el parámetro `$publish` de `EntityConfigSynchronizer::syncEntity`; la lista de entidades configurables queda en `EntityConfigSynchronizer::entidades()` (también excluye `RevisionMetadata`).

### Frontend

- `core/metadata/sincronizacion.ts` (`useMetadataStore`, global): al arrancar pide el manifiesto (espera hasta 1,5 s; si tarda, la app monta con lo guardado y lo aplica cuando llega). Con sesión escucha Mercure, la cabecera de cualquier respuesta (`core/respuestas.ts`, el `fetch` de `core/http` y del cliente GraphQL) y el regreso a la pestaña (si la última consulta tiene más de un minuto). Los avisos seguidos se juntan: 1 s sin avisos nuevos, como mucho 5 s desde el primero; si al vencer ya tiene lo anunciado, no consulta.
- **Schema**: se vuelve a pedir la introspección solo si la huella del servidor no es la guardada (o se pidió recargar todo después), sin caché de Apollo. Cada navegador espera al azar hasta 5 s antes (todos reciben el aviso a la vez). La huella de cada `EntitySchema` (`core/metadata/huellas.ts`) dice qué entidades cambiaron sin guardar el schema anterior; las que ya no existen se olvidan (su store y lo que guardó cada usuario).
- **Configuración**: cada store de entidad guarda `revisionConfig` (la revisión del manifiesto con que la pidió) y `huellaEsquema`. `init()` pide la configuración solo si el manifiesto dice que cambió después (o se pidió recargar todo, que además descarta los datos guardados); sin manifiesto, una vez por página. Si cambió la metadata de la entidad, `reconciliar()` ajusta el estado con reglas genéricas (`core/metadata/reconciliar.ts`): quedan los filtros cuyo argumento sigue existiendo con la misma forma y el orden sobre campos ordenables, el filtro a la medida se quita si perdió un argumento, se descartan `items`, `item` y `fullList` y se vuelve a la primera página. Las stores de otros usuarios del navegador se ponen al día cuando se usan. Esto reemplaza al `WeakSet`.
- **Pantallas abiertas**: las stores en uso que quedaron viejas suben su versión (`versionDe`). El listado genérico se recarga solo (salvo con una celda en edición); el formulario genérico no se rearma solo —se perdería lo escrito—: muestra un aviso con «Recargar».
- **Aviso publicado**: `AvisoMetadata.vue` muestra la nota con «Después» y «Recargar», apilado con el de versión nueva de la app; nunca recarga solo.
- **Red de seguridad**: un error de GraphQL que delata un schema viejo (`core/graphql/desfase.ts`: «Cannot query field…», «Unknown argument…», campo de input desconocido u obligatorio nuevo…) vuelve a pedir la introspección aunque el manifiesto no lo diga, como mucho una vez por minuto, y avisa si algo cambió.
- **Pantalla** `/configuracion/publicacion` (`features/metadata-publicacion/`, datos en `core/metadata/publicacion.ts`): modo, pendiente, nota, aviso, recargar todo e historial. Enlace «Publicar configuración» junto a «Configuración de entidades» en la administración (migración `Version20261020220000`).

## Consecuencias

- Un navegador abierto se pone al día sin recargar: una configuración guardada llega en uno o dos segundos y un despliegue en unos segundos más; la primera carga tras esta versión vuelve a pedir el schema y la configuración una vez.
- En estado estable una carga de página hace una petición al manifiesto (304) y ninguna a la configuración ni a la introspección; antes pedía la configuración de cada entidad usada.
- `SCHEMA_VERSION` queda para cambios del *formato* de la copia guardada, no para cambios del schema.
- El modo manual decide cuándo se enteran los navegadores, no es un sistema de borradores: un navegador que pide la configuración por primera vez recibe la vigente aunque no esté publicada.
- Un renombre de campo es, sin código por campo, un campo que se va y otro que llega: se pierden los filtros, el orden y el ancho del viejo. Si hiciera falta conservarlos, el nombre anterior podría declararse en la propiedad (`ApiProperty` `extraProperties`) y publicarse en el manifiesto; no se hizo.
- Los datos en caché (`fullList`) siguen sin vencer por sí solos: se renuevan con «recargar todo» o cuando cambia la metadata de su entidad.
- El entrypoint necesita reconstruir la imagen del backend para incluir el comando; mientras tanto, la primera lectura del manifiesto publica el schema y `make entity-setup` sincroniza la configuración (que el listener publica al terminar).
