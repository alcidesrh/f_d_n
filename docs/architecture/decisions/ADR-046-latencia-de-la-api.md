# ADR-046: Latencia de la API: sin sesión, cargas por lotes y tablero precalculado

**Estado:** Aceptada

## Contexto

Objetivo: p95 de las peticiones por debajo de 200 ms. Medido en producción (logs de acceso de Caddy, 2026-10-29) el p95 era de 295 ms, con 18 de 41 rutas por encima. Las causas, perfiladas con el kernel `prod` sobre la base real:

- **La sesión serializaba las peticiones en paralelo.** El firewall `main` era *stateful*: cada petición con Bearer abría una sesión PHP (`PHPSESSID`) y el manejador de archivos la bloquea durante toda la petición. La SPA pide 6–10 cosas a la vez con la misma cookie y esperaban una detrás de otra: la misma ráfaga tenía p95 de 157 ms con la cookie y de 55 ms sin ella; detrás de una lenta (la lista de salidas), todas tardaban lo que ella.
- **N+1 que no parecía N+1.** `Parada::enclave` apunta a `Enclave`, raíz de una herencia de tabla única (`Estacion`). Doctrine no puede dejar perezoso un *to-one* hacia una clase con subclases: trae cada enclave con su propia consulta al hidratar la parada. Recorrer `Ruta::getOrigen()`/`getDestino()` de muchas salidas hacía una consulta por ruta y otra por enclave: 165 consultas en la lista de salidas de la venta, 164 en la de gestión. Igual, sin la herencia, con los roles y usuarios de cada caja de `/administracion` (94).
- **Tarifas salida por salida**, y de todos los pares de paradas aunque la lista solo ofrezca los que salen de donde se sube.
- **El tablero agrega varias semanas de boletos** (dos consultas de ~100 ms cada una sobre ~50 mil filas) y la portada lo vuelve a pedir cada 5 minutos.
- **La metadata de API Platform se recalculaba.** Su fábrica de Doctrine (prioridad -50) decora por fuera de la caché (-10): cada `create()` le vuelve a preguntar a Doctrine si la clase es entidad (leyendo atributos), y armar el schema de GraphQL lo llama cientos de veces: 30–40 ms por petición GraphQL.
- **Los estáticos del frontend salían sin `Cache-Control`** (ni validadores): el navegador volvía a bajar fuentes y bundles.

## Decisión

- **API sin sesión**: `stateless: true` en el firewall `main`. Todo cliente manda su Bearer (la SPA lo guarda en Pinia; la página web usa rutas públicas; las descargas son `fetch` + `blob:`; los archivos del chat y las fotos, URL firmada). `POST /auth` ya solo responde con Bearer.
- **Cargas por lotes** donde una lista recorre relaciones:
  - `Ruta\ParadasDeRutas::cargar()` trae de una vez paradas, enclaves y tiempos de las rutas de una lista (lo usaban solo el calendario; ahora también la gestión de salidas y la venta).
  - `ResolutorTarifa::precargar()` trae las tarifas de todos los trayectos de la lista en una consulta, y la lista de la venta tarifa solo los trayectos que salen de donde se sube (el resultado es el mismo: la tarifa de un trayecto no depende de los demás).
  - `PanelAdministracion` inicializa roles y usuarios de cajas y enlaces en cuatro consultas.
- **Tablero precalculado**: `Tablero\TableroEnCache` sirve una copia por periodo y empresa desde `cache.tablero` (PostgreSQL: el backend y el worker no comparten disco). La tarea `refrescar_tablero` del programador (cada minuto) rehace las copias que alguien miró en los últimos 10 minutos. Una copia de más de 2 minutos o de otro día se calcula en la petición, como antes. La consulta de movimientos agrupa primero por ids y pone los nombres a los grupos.
- **Metadata en memoria**: `Acceso\Api\MetadataEnMemoria` decora la fábrica por fuera de todo (-100) y guarda la metadata de cada clase durante la vida del proceso.
- **Caché de estáticos** en `frontend/server.ts`: `assets/` (con hash) un año e `immutable`; fuentes, imágenes e íconos un día; `index.html`, el service worker, el manifiesto y el schema, `no-cache`.

## Consecuencias

- Resultado (kernel `prod`, base de producción, medianas): lista de salidas de la venta 403 → 157 ms (con estación 148 → 77); tablero 326 → 15 ms; gestión de salidas 157 → 66 ms; administración 111 → 26 ms; listados GraphQL 61–87 → 31–47 ms. Ninguno de los endpoints medidos pasa de 200 ms en serie.
- **El tablero puede mostrar cifras de hasta ~1 minuto** (2 si el worker se atrasa); `generado` dice de cuándo son. La portada ya se refrescaba cada 5 minutos. Una alerta que se resolvió puede seguir visible ese rato.
- La tarea nueva sale como "programador detenido" hasta su primera corrida, como cualquier tarea recién agregada: tras desplegar, `make tareas t=refrescar_tablero`.
- Un cambio de metadata de API Platform (atributos de una entidad) exige reiniciar los workers de FrankenPHP; el despliegue ya lo hace.
- Un endpoint nuevo que recorra rutas de muchas salidas debe usar `ParadasDeRutas`; cualquier *to-one* hacia `Enclave` (paradas, trayectos, encomiendas) se carga una consulta por fila si no viene en un `JOIN` o ya está en memoria.
