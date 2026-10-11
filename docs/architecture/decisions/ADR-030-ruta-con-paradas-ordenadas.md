# ADR-030: Ruta con paradas ordenadas; el trayecto queda como par comercial

**Estado:** Aceptada (implementada en 8 fases, 2026-10; ver "Plan")

## Contexto

Un bus recorre una **secuencia ordenada de paradas**. Lo que un cliente pide en taquilla o en la página es un par origen→destino, y la mitad de las veces ese par no son las puntas del bus: en la base migrada, 43 588 de 81 887 boletos (53 %) son de un tramo intermedio. El caso intermedio es tan normal como el directo.

El modelo no guardaba esa secuencia. `Salida` apuntaba a un `Trayecto` (único por par de enclaves, ADR-012) y el orden de sus paradas se **deducía** de sus `Subtrayecto` —todos los pares hacia adelante que generaba el migrador— con un orden topológico en `App\Venta\Itinerario`. Consecuencias medidas:

- `Subtrayecto.position` estaba vacío en las 7 516 filas; el orden era siempre una inferencia.
- 23 trayectos padre (unas 2 600 salidas) tenían pares contradictorios: el migrador de estáticos **inventaba** la ruta inversa de cada ruta (`REV-*`, paradas al revés) y, como el trayecto es único por puntas, las rutas distintas del legado con las mismas puntas se mezclaban en un mismo padre. Hay ciclos, y el orden salía del desempate.
- La hora en una parada se tomaba de `Trayecto.duracionEstimadaMinutos` del par origen→parada, que nunca se migró (0 trayectos con duración): las horas intermedias eran siempre desconocidas.
- La restricción única `(asiento, trayecto, salida)` no impide vender A→C y B→D en el mismo asiento; solo lo evitaba el bloqueo de la aplicación.
- El tramo de un boleto se recalculaba al leer: si cambiaban los subtrayectos, cambiaba el tramo de boletos ya vendidos.
- Comparado con las rutas del legado, el orden deducido era **distinto en 1 710 de 4 193 salidas (41 %)**, con 33 525 boletos (RUT113: 503 salidas, RUT101: 367, RUT104: 170, …): esos boletos ocupaban tramos que no eran los suyos.

El legado **sí** guarda la secuencia: `ruta` (origen, destino, km) + `ruta_estacion_item` (paradas intermedias con `posicion`, sin repetir ni incluir las puntas) + `tiempo` (minutos desde el origen de la ruta hasta cada estación, **por clase de bus**: p. ej. RUT001 a Santa Elena 600 min en Clase Oro y 680 en Económica). 124 rutas (115 activas), 89 con su inversa propia en el legado, 9 pares de puntas servidos por más de una ruta; `tiempo` cubre 50 rutas. La salida del legado llega a su ruta por `salida.itinerario_id → itineario.ruta_codigo`; el boleto guarda sus estaciones de subida y bajada (`estacion_origen_id`, `estacion_destino_id`).

## Decisión

Se guarda el hecho (la secuencia) y se derivan las consecuencias (los pares vendibles, el tramo, la hora en cada parada).

### Modelo

| Entidad | Qué es | Campos |
|---|---|---|
| **Ruta** (nueva) | El camino con paradas que recorre un bus. Independiente de empresa y fecha. Equivale a `ruta` del legado. | `nombre`, `kilometros`, `activo`, `legacyId` (código). Origen y destino son su primera y última parada. |
| **Parada** (nueva) | El paso de una ruta por un enclave. | `ruta`, `enclave`, `orden` (0 = origen), `minutos` desde el origen (opcional), `km` (opcional), `permiteSubir`, `permiteBajar`. Únicas `(ruta, orden)` y `(ruta, enclave)`. |
| **ParadaTiempo** (nueva) | Minutos hasta la parada para una clase de bus, cuando difiere. | `parada`, `busClase`, `minutos`. Única `(parada, busClase)`. |
| **SalidaParada** (nueva) | El horario de una salida concreta: a qué hora pasa por cada parada. | `salida`, `orden`, `enclave`, `hora`, `precision` (`exacta` en el origen, `estimada` con minutos conocidos, `desconocida`: se usa la hora del origen), `permiteSubir`/`permiteBajar` (copiados de la parada, para buscar sin unir la ruta). Única `(salida, orden)`; índice `(enclave, hora)`. |
| **Salida** | Viaje concreto. | `ruta` en lugar de `trayecto`. |
| **BoletoAsiento** / **ReservaAsiento** | Asiento vendido / apartado. | `ordenSube`, `ordenBaja` (el tramo `[sube, baja)`); `trayecto` se queda: es el par que viaja el pasajero (tarifa, factura, reportes); el tramo lo fija la venta y ya no se recalcula. |
| **EsquemaSalida** | Plantilla del programador. | `ruta` en lugar de `trayecto`. |
| **Trayecto** | Solo el **par comercial** origen→destino: lo que se pide, se tarifa y se factura. | Igual; pierde `subtrayectos` y la duración. Al guardar una ruta existe el de cada par de sus paradas en orden (el catálogo de lo que se puede tarifar con el CRUD de tarifas); los que no son par de ninguna ruta ni tienen tarifa ni boletos se eliminan. |
| **Subtrayecto** | — | Se elimina: un subtrayecto es cualquier par `i < j` de paradas de una ruta. |

Reglas:

- **Pares vendibles** de una salida: paradas `i < j` con `permiteSubir` en `i`, `permiteBajar` en `j` y tarifa asignable para el trayecto `(enclave_i, enclave_j)` (la regla "solo se vende lo tarifado" de ADR-021 sigue igual).
- **Minutos por parada**: `ParadaTiempo` de la clase del bus de la salida; si no hay, `Parada.minutos`. Al migrar, `Parada.minutos` es el **menor** de las clases: para subir, llegar antes es seguro y llegar tarde pierde el bus.
- **`SalidaParada` es una foto**: se genera al crear la salida y al cambiar su fecha, hora o bus. Editar una ruta regenera el horario de sus salidas futuras **sin boletos ni reservas**; las que ya vendieron conservan el suyo, así un boleto nunca cambia de tramo. Por eso la ruta no necesita versiones.
- **"Cobrar el trayecto completo"** (ADR-021) no cambia: el boleto guarda el trayecto que viaja y su tramo `[ordenSube, ordenBaja)`; solo el precio sale del trayecto completo.
- **Sin solapes por base de datos**: `ex_boleto_asiento_tramo`, `EXCLUDE USING gist (salida_id WITH =, asiento_id WITH =, int4range(orden_sube, orden_baja) WITH &&)` sobre boletos vivos (extensión `btree_gist`), más `CHECK (0 <= orden_sube < orden_baja)` en boletos y reservas. Reemplaza el índice único parcial `(asiento, trayecto, salida)`. Doctrine no mapea restricciones de exclusión: `App\Doctrine\Schema\ExclusionConstraintsSchemaManager` ignora los índices `ex_*` para que `schema:update` y `migrations:diff` no los borren; se crean solo en migraciones. Las reservas, que vencen solas, siguen validándose en la aplicación con la salida bloqueada.
- **Migración**: los boletos anulados y reasignados del legado se migran todos (no ocupan); entre los vivos, uno que se solapa con otro en el mismo asiento se omite y se cuenta (`boleto_asiento_repetido`). Antes se descartaban también los anulados repetidos.

### Búsqueda

Taquilla y página hacen la misma consulta: salidas que pasan por O y después por D (`SalidaParada` O con `orden` menor que D), con la **hora en O** dentro del día pedido. Una salida que arranca a las 22:00 y pasa por O a la 01:00 aparece el día siguiente, no el anterior. Los asientos libres que se muestran son los del tramo pedido.

### Presentación

El cliente piensa en su viaje: en primer plano la hora en su origen, la llegada (aprox. si no es exacta, "+1 día" si cambia de día), las paradas intermedias, los libres de su tramo y el precio; la ruta del bus ("Bus Guatemala → Flores, sale de Guatemala 04:30") como contexto. En taquilla se recomienda el asiento cuyo hueco libre encaja mejor con el tramo, para no fragmentar el bus.

### Migración

- `ruta` → `Ruta` 1:1 (`legacyId` = código); paradas: origen, `ruta_estacion_item` por `posicion`, destino. No se inventan rutas inversas: las que existen en el legado se migran como cualquier otra.
- `tiempo` → `Parada.minutos` (menor de las clases) y `ParadaTiempo` (las clases que difieren). Clase del legado = `BusClase` (mismo id).
- Salida → `Ruta` por `itineario.ruta_codigo`. Boleto → `ordenSube`/`ordenBaja` por sus estaciones de subida y bajada; un boleto fuera de la ruta ocupa la salida completa y se cobra su trayecto suelto (como hoy).
- Una **agencia** del legado (estación tipo 4) que es parada o punta de una ruta también es un **lugar**: se crea su `Enclave` (Belice City, Belice, Chetumal; rutas RUT201/202/212–215). La agencia como vendedora sigue siendo `Agencia`. Antes, esas rutas y sus salidas (unas 1 350 en el legado) no se migraban.
- La base migrada antes de este ADR se rellenó una vez con un comando transitorio (`app:ruta:rellenar`, retirado en la fase 5: dependía de `salida.trayecto_id`); otra base anterior se vuelve a migrar desde el legado. Primer relleno: 124 rutas, 1 215 paradas (502 con minutos, 205 tiempos por clase), 4 193 salidas con su ruta y horario (56 % de las paradas con hora estimada, el resto desconocida porque `tiempo` solo cubre 50 rutas), 81 887 boletos con tramo y **ningún solape** entre boletos vivos.

## Plan

Cada fase deja la aplicación funcionando y actualiza docs y borra lo que queda sin uso en el mismo cambio.

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Este ADR y el glosario | Hecha |
| 1 | Entidades nuevas (aditivas), migrador desde el legado, relleno de la base existente | Hecha |
| 2 | `Itinerarios` y las horas leen de `Parada`/`SalidaParada`; fuera el orden topológico | Hecha: `Itinerarios::deSalida` lee `salida_parada`; el programador y la edición fijan ruta (`RutaDeTrayecto`, transitorio) y horario |
| 3 | Búsqueda por hora en el origen; libres por tramo | Hecha: `BuscadorSalidas` (taquilla y web); `vendidos` de la lista de taquilla por tramo; orígenes, destinos y directorio de la web desde paradas de rutas activas con tarifa |
| 4 | Ocupación por `orden`; restricción de exclusión | Hecha (antes que la 3: la búsqueda cuenta libres por tramo con el orden). Verificado: venta de tramos que no se solapan en el mismo asiento, rechazo del solape en la aplicación y en la base |
| 5 | `Salida.ruta` y `EsquemaSalida.ruta` obligatorias; fuera `Salida.trayecto` | Hecha: el programador, la edición, los esquemas y el filtro eligen ruta (`rutaId`); la duración para choques es la de la ruta; el trayecto completo se deduce del horario; los avisos de salidas anuladas llegan a todas las estaciones donde se sube; la bitácora de salidas guarda `ruta`. El sincronizador de configuración de entidades ahora quita los campos que ya no existen |
| 6 | Gestión de rutas (editor de paradas) y tarifas por par | Hecha: `/rutas` (lista, nueva, ruta de regreso, duplicar, eliminar sin uso) y `/rutas/:id` (paradas reordenables, minutos/km desde el origen, sube/baja, minutos por clase, mapa). REST `/api/gestion-rutas/*` (ver: `salida.ver`; editar: `ruta.editar`, en el permiso "Gestion Salidas"). Los minutos que retroceden se avisan, no se rechazan: hay 6 rutas del legado así (p. ej. RUT001 con Morales y El Estor, ramales) |
| 7 | Presentación en taquilla, página y boleto | Hecha: lista de taquilla con el viaje del cliente (`viaje`: sube/baja con hora, ≈ si estimada, llegada +N, paradas en medio; ruta del bus aparte) y asiento recomendado por mejor encaje (`Recomendacion`, ocupación de taquilla `recomendados`); tarjeta web con "aprox.", "+1 día" y la ruta del bus; boleto (PDF, correo, ticket de 80 mm, web) con la precisión de la hora de subida: si no se conoce, dice la hora a la que el bus sale de su origen en vez de presentarla como la del pasajero |
| 8 | Retiro de `Subtrayecto`, trayectos huérfanos y documentación final | Hecha: fuera `subtrayecto`, `trayecto.distancia_km`/`duracion_estimada_minutos`/`legacy_id` y 114 trayectos huérfanos (casi todos de las rutas inversas inventadas); el migrador ya no crea trayectos "padre" ni rutas inversas: `RutasLegado` asegura el trayecto de cada par (`App\Ruta\Trayectos`) y las tarifas del legado crean el de su par si falta. `App\Venta\Itinerario` conserva su nombre: es la vista de venta del horario de una salida, no la ruta |

## Alternativas descartadas

- **Mantener los subtrayectos y completar `position`**: sigue guardando n² pares para describir n paradas y no resuelve dos caminos distintos con las mismas puntas.
- **Calcular la hora en cada parada al leer** (`fecha + minutos`): no deja a una salida apartarse de su ruta (desvío, parada omitida) y obliga a recalcular en cada búsqueda; la foto cuesta ~15 filas por salida.
- **Versionar la ruta**: innecesario con el horario como foto por salida.
- **Llamar "Itinerario" a la ruta**: el término existe en el legado con otro sentido (plantilla de horarios); "ruta" es el nombre coherente con lo que es y con su origen.

## Consecuencias

**Positivas:** el orden de las paradas es un dato, no una inferencia; varias rutas pueden unir las mismas puntas; horas por parada y por clase de bus; doble venta imposible a nivel de base; búsqueda por índice; un boleto no cambia de tramo.

**Negativas:** una refactorización amplia (venta, salidas, seguimiento, reportes, página, migrador); `SalidaParada` duplica datos derivables a cambio de estabilidad; la migración depende del legado para rutas y tiempos.

Reemplaza en parte a [ADR-012](ADR-012-trayecto-canonico-por-enclaves.md) (el trayecto deja de describir el camino) y enmienda [ADR-021](ADR-021-venta-de-asientos.md) (itinerario y ocupación).
