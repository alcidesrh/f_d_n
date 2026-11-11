# ADR-056: Motor de importación dentro de la sincronización, un solo entity manager, índices que faltaban, arranque del frontend en capas y fuera la migración masiva

**Estado:** Aceptada

## Contexto

Revisión general del repositorio con tres preguntas: qué sobra en la arquitectura, qué se lee más lento de lo que debería y qué código ya no hace falta.

- **La migración masiva del legado ya se hizo y no se repite**, pero la sincronización (ADR-042), que sí sigue hasta que el personal se mude, traía cada salida con el código de esa migración (`App\Migration`). Las dos cosas estaban en el mismo módulo: no se podía quitar una sin romper la otra, y el ADR-042 decía que la sincronización «se quita entera» cuando en realidad dejaba medio motor fuera.
- **Dos entity managers** (ADR-002): el segundo, sobre SQL Server, con 112 entidades (`EntitySistemaFdn`) y un driver dblib propio. Nada los usaba: la migración y la sincronización leen el legado con PDO.
- **El motor se conectaba al legado al construirse**: los constructores de `Migrador` y compañía configuraban el `PDO` (`setAttribute`), y eso abre la conexión. Bastaba con que el contenedor armara uno de esos servicios para hablarle a la base de producción del legado.
- **El catálogo genérico `Status`** (ADR-014 lo dejó pendiente): `cliente`, `piloto`, `usuario` y `boleto_venta` llevaban un `status_id` con su llave foránea y su índice, que se mantenían en cada venta, y nada lo leía ni lo escribía.
- **Consultas que recorrían la tabla entera.** Medido con `EXPLAIN ANALYZE` sobre una base de prueba con volumen parecido al real (800 mil clientes, 1 millón de ventas, 1,3 millones de boletos, 900 mil facturas, 1,5 millones de filas de bitácora): la sincronización buscaba boletos por `legacy_id` y facturas por `uuid` sin índice en cada ciclo; el listado de boletos y los reportes filtran por fecha de venta y el único índice empezaba por `estado`; la búsqueda de clientes de la pantalla de venta (`LIKE '%texto%'`) tardaba 170 ms.
- **Frontend:** el arranque bajaba 11 archivos y un cambio de una línea en cualquier pantalla invalidaba 143 archivos (2,9 MB) de la copia sin conexión, porque el archivo de entrada nombraba todos los trozos perezosos y casi todos lo importaban. `core/` y `shared/` importaban el router de `app/` (contra ADR-017), que arrastraba esa lista a su trozo.

## Decisión

### Arquitectura

- **El motor de importación vive en la sincronización**: `App\Sincronizacion\Legado\Importacion` (`Migrador`, `Mapeador`, `DependenciasLegado`, `RutasLegado`, `InferenciaBus`, `EncomiendasLegado`, `MigradorIAM`, `RolesUsuarioLegado`, `LectorLegado`, `PdoLegado`, `SondaLegado`). Los dos comandos que todavía sirven al personal que se muda van con él: `app:acceso:alcance-legado` y `app:encomienda:legado` (`App\Sincronizacion\Comando`). Quitar la sincronización vuelve a ser borrar una carpeta.
- **La conexión al legado se abre al usarla.** `SondaLegado::crearPdoReconectable()` configura cada conexión cuando la crea; ningún constructor toca el `PDO`.
- **Un solo entity manager.** Fuera la conexión `systemfdn` de Doctrine, su entity manager, `EntitySistemaFdn` y el driver `PDODblib`. Reemplaza a ADR-002. El `PDO $oldPdo` de `config/services.yaml` (variables `systemfdn_*`) queda: es el de la sincronización.
- **`FormFieldConfig` importa `ApiProperty`**: sin el `use`, el atributo `readable: false` de `entityConfig` no se aplicaba.
- **Fuera `Status`**: la entidad, su trait, `TimeLegacyStatusBase` y las cuatro columnas (migración `Version20261111100000`, que también limpia su configuración de listado y formulario, sus enlaces y las capacidades `status.*` de los roles). Cierra ADR-014.

### Rendimiento

Índices nuevos (migración `Version20261111110000`). Tiempos de `EXPLAIN ANALYZE` en la base de prueba, antes y después:

| Consulta | Índice | Antes | Después |
|---|---|---|---|
| Sincronización: boletos por `legacy_id` | `idx_boleto_asiento_legacy` (parcial) | 75,6 ms | 0,2 ms |
| Sincronización: factura por `uuid` | `idx_factura_uuid` | 25,3 ms | 0,1 ms |
| Cortesías del día de un usuario | `idx_boleto_venta_creada` | 39,5 ms | 0,2 ms |
| Ventas que esperan factura (tablero, tarea que certifica) | `idx_boleto_venta_fel_pendiente` (parcial) | 53,2 ms | 0,1 ms |
| Incidencias del día en la bitácora | `idx_bitacora_fecha` | 52,0 ms | 0,6 ms |
| Resumen diario de la bitácora | `idx_bitacora_fecha` | 47,7 ms | 0,5 ms |
| Cliente por nombre (dos palabras) | `nat_cliente_busqueda` (GIN, trigramas) | 173,7 ms | 8,5 a 13 ms |
| Cliente por un nombre poco común | `nat_cliente_busqueda` | 165,9 ms | 0,1 ms |
| Cliente por NIT (prefijo) o documento | `nat_cliente_nit_prefijo`, `idx_cliente_documento` | 34,4 ms | 0,2 a 0,6 ms |

- **Índices `nat_*`**: los que el mapeo de Doctrine no sabe expresar (clase de operadores, GIN sobre una expresión) solo existen en migraciones, como las restricciones `ex_*` de ADR-030, y `ExclusionConstraintsSchemaManager` los ignora en el diff. La expresión de `nat_cliente_busqueda` es, letra por letra, la que arma `App\Venta\Clientes::buscar`: si cambia una, cambia la otra, o la búsqueda vuelve a recorrer la tabla sin avisar.
- **Arranque del frontend en tres capas** (`vite.config.ts`, `codeSplitting.groups`): `base` (lo de `node_modules` que usa el arranque), `nucleo` (`core/` y `shared/`) y la entrada (`app/`). La entrada es la única que nombra los trozos perezosos, y nadie la importa.
  - Para que eso se sostenga, **`core/`, `shared/` y `features/` no importan nada de `@/app`** (ya lo pedía ADR-017): el router se toma con `useRouter()`/`useRoute()` y `core/auth/unauthorized.ts` lo recibe con `usarRouter()` desde `app/router.ts`.
  - La copia sin conexión ya no precarga los juegos de íconos completos (`material-symbols-*`, `iconMeta-*`): solo los usa el selector de íconos de la configuración.

| | Antes | Después |
|---|---|---|
| Archivos de JavaScript del arranque | 11 (928 kB, 287 kB gzip) | 4 (925 kB, 280 kB gzip) |
| Copia sin conexión | 256 archivos, 7,7 MiB | 245 archivos, 6,9 MiB |
| Lo que vuelve a bajar un usuario tras cambiar una pantalla | 143 archivos, 2,9 MB | 9 archivos, 0,29 MB |

### Limpieza

- **La migración masiva**: `App\Migration` (migradores estáticos, jobs, limpiador, reseteador, indicadores), `app:migrar:todo`, `MigracionController`, la pantalla `/migracion`, la capacidad `migracion.ejecutar` (migración `Version20261111120000`: la quita de los roles, de los menús y de la administración), el pool `cache.migracion` y `make migrar-todo`. Del motor que queda se fueron los métodos que solo usaba la migración masiva (`Migrador::migrarSalida` pasa a `migrarSalidas(filas, salida)`).
- **Archivos que no eran del proyecto**: registros de acceso del legado (20 MB, con direcciones IP), la bitácora de un job de migración, una carta personal, `opencode.js`, `update-deps.sh` (la plantilla de Symfony Docker: su primer paso borra los volúmenes), `backend/compose.yaml` y su override (una copia vieja del `compose.yaml` de la raíz, con la contraseña del legado como valor por defecto) y `backend/resources/enclave_coordenadas.json`.

## Consecuencias

- **Desplegar**: `make deploy` aplica las tres migraciones. `Version20261111110000` crea la extensión `pg_trgm` (en PostgreSQL 13 o más la puede crear el dueño de la base) y sus `CREATE INDEX` bloquean las escrituras de cada tabla mientras se construyen: segundos con el volumen actual, mejor en horas de poca venta.
- **Los índices cuestan al escribir.** Cada venta mantiene un índice más en `boleto_venta` (dos mientras espera factura; a cambio ya no mantiene el de `status_id`); el de `boleto_asiento` solo pesa en los boletos que vienen del legado; cada cliente nuevo mantiene tres. No se midió el costo al insertar: si crear clientes se resiente, el primero a revisar es el GIN de `cliente`.
- **Una base hecha con SchemaTool** (`doctrine:schema:create`, la de las pruebas) no tiene los `nat_*` ni los `ex_*`: todo funciona igual, la búsqueda de clientes es más lenta.
- **Una base nueva con datos reales sale de un respaldo de la desplegada**, no del legado: la sincronización trae lo que cambia y crea a demanda lo que una salida necesita (empresa, estación, bus, cliente, usuario), pero ya no hay quien traiga los catálogos enteros. Lo borrado está en el historial de git (hasta el commit `5c18119`).
- **Ya no existe la cuenta `admin` de respaldo** (contraseña de cuatro espacios) que creaba el reseteo de la migración. En desarrollo, `make e2e-usuario`. Conviene comprobar que esa cuenta no haya quedado en la base desplegada.
- **Las entidades del legado ya no están como referencia** para lo que falta portar (manifiestos distintos del interno y del piloto, `CONTEXT.md`): la fuente es el legado mismo.
- **No se tocó el renderizado de las pantallas.** Sin el stack levantado con datos reales no hay dónde medirlo, y cambiar componentes sin medir es adivinar. Lo que se midió y se arregló es el arranque y la caché.
- **Herramientas que convendría agregar** (no se agregó ninguna):
  - `pg_stat_statements` y `auto_explain` en PostgreSQL: dicen qué consultas son lentas con los datos y el uso reales, que es lo que esta revisión tuvo que simular.
  - Un visualizador del bundle en el build (por ejemplo `rollup-plugin-visualizer` o `sonda`): aquí se hizo a mano con los sourcemaps.
  - `web-vitals` reportando a la bitácora de actividad (ADR-051) y Lighthouse en el despliegue, para ver el arranque que tienen de verdad las estaciones.
  - El panel de rendimiento de Vue DevTools para las pantallas pesadas (venta, listados, croquis), y el perfilador de Symfony o Blackfire para un endpoint concreto.
  - PHPStan: `backend/AGENTS.md` lo pide y no está instalado. Habría detectado el error que se corrigió aquí en `FormFieldConfig`.
