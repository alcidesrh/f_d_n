# ADR-025: Seguimiento de buses en recorrido (mapa en tiempo real, GPS simulado)

**Estado:** Aceptada

## Contexto

Operaciones necesita ver en un mapa dónde van los buses que están en recorrido, filtrando por empresa y por salida. Todavía no hay GPS instalados, y las salidas en curso viven solo en el legado (SQL Server, `salida.estado_id = 3` iniciada): el modelo nuevo aún no tiene enclaves con coordenadas ni salidas reales.

## Decisión

### Fuente de datos: el legado, solo lectura

`App\Seguimiento\LegadoEnRecorrido` consulta por PDO (`oldPdo`, con el preflight de `SondaLegado`) las salidas de las últimas 30 h y las estaciones de sus rutas (origen, intermedias por `posicion`, destino). El criterio es el horario, no el estado: el personal casi nunca marca "iniciada". Salió toda salida que pasó su hora, no está cancelada (4) ni finalizada (5) y tiene al menos un boleto vigente (emitido, chequeado o en tránsito: ni anulado, reasignado ni cancelado); las marcadas "iniciada" (3) salen aunque no tengan boletos. El detalle avisa cuando el sistema no la tiene como iniciada. Se descartan las que ya llegaron según el cronograma (más 10 min de gracia).

### Posición inferida (`PlanDeViaje`)

Dada la hora de salida, los km de la ruta y una velocidad media (68 km/h en marcha, ±8 % estable por salida, 4 min de espera por estación intermedia y 20 min de descanso en la intermedia más cercana a la mitad en rutas ≥ 300 km), el plan da la posición en cualquier instante, el rumbo y la próxima estación. Es determinista: el servidor devuelve el cronograma (`paradas` con `llegada`/`salida` en epoch) y el navegador anima el bus con el reloj (`core/seguimiento/posicion.ts`, espejo del PHP) y refresca la lista cada 30 s.

### Trazado de la ruta (`TrazadoDeRuta`)

Solo 15 de 225 estaciones del legado tienen GPS, y en esas la latitud y la longitud vienen a veces intercambiadas. Se resuelve cada estación por GPS → catálogo por nombre (`Gazetario`) → centro del departamento. Como el legado no garantiza que las intermedias sean un recorrido ordenado ni estén bien ubicadas: las intermedias solo ubicadas por departamento se descartan, las consecutivas a menos de 2 km se funden y las que obligan a un desvío mayor que el trayecto directo (y 30 km) se descartan. Origen y destino son obligatorios; si no se ubican, la salida no se muestra y la pantalla avisa cuántas quedaron fuera. Los km se reparten según la distancia en línea recta pero suman los `kilometros` de la ruta. El mapa une las estaciones en línea recta (sin geometría de carretera); para que las rutas que comparten tramo no se tapen, cada ruta se dibuja arqueada con una curvatura propia y estable según su código (`core/seguimiento/arco.ts`), y el bus se mueve por el mismo arco.

### GPS real: punto de enchufe

`App\Seguimiento\FuenteGps::ultima(busCodigo): ?Posicion` (hoy `SinGps`). Con una lectura de menos de 5 min, `SeguimientoBuses` la usa en lugar de la simulación (`fuente: 'gps'`, sin cronograma) y el frontend la pinta tal cual. Para pasar a datos reales basta implementar la interfaz.

### API y permisos

`GET /api/seguimiento/buses` (permiso `salida.ver`: es otra vista de las mismas salidas). Responde 503 `legado_no_disponible` si el legado no contesta.

### Frontend

Ruta `/seguimiento` (`features/seguimiento/`, Leaflet con teselas de OpenStreetMap), con `meta.label`/`icon` para aparecer en el sidebar derecho (en la lista de respaldo de rutas; si el usuario tiene menús configurados hay que añadir el ítem en `/configuracion/menus`). Filtros por empresa y salida (reglas puras en `core/seguimiento/filtro.ts`); elegir una salida enfoca el bus y su ruta.

## Consecuencias

- Dependencia nueva: `leaflet` (+ `@types/leaflet`), sin alternativa ya presente para mapas.
- Mientras las estaciones no tengan coordenadas reales, los trazos y estaciones intermedias son aproximados (el mapa marca con círculo vacío las ubicadas por nombre). Registrar el GPS de las estaciones mejora el trazado sin cambiar código.
- Al migrar salidas al modelo nuevo, `LegadoEnRecorrido` se reemplaza por una consulta a `Salida` + `Trayecto`/`Enclave`; `PlanDeViaje`, `FuenteGps` y el frontend no cambian.

## Coordenadas de los enclaves

`php bin/console app:enclave:geocodificar [--dry-run]` busca cada enclave en Nominatim (OpenStreetMap, 1 petición/s; Photon como respaldo para erratas), limitado a Guatemala y a su departamento (los internacionales, a El Salvador, Honduras, Belice y México y sin coincidencias ambiguas). Solo rellena los que no tienen coordenadas y corrige el nombre local cuando es una errata evidente (parecido ≥ 0.7, misma inicial). Un enclave sin resultado copia las coordenadas de otra terminal del mismo lugar ("Aguilar Batres1"). `SeguimientoBuses` usa estas coordenadas para las estaciones del legado sin GPS (los enclaves reutilizan el id de la estación). `Mapeador::estacion` ya normaliza la latitud/longitud intercambiadas del legado.
