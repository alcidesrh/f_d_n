# ADR-055: Mapas propios (MapLibre, teselas PMTiles y Valhalla)

**Estado:** Aceptada. Reemplaza el mapa de ADR-025 y ADR-050 (Leaflet con teselas de OpenStreetMap) y el
servicio de rutas de los km por carretera (el servidor de demostración de OSRM).

## Contexto

Los cuatro mapas (editor de rutas `/rutas/:id`, `/tarifas/mapa`, `/seguimiento` y
`/seguimiento/reproducir`) usaban Leaflet con las teselas de `tile.openstreetmap.org`, y los km por
carretera de las rutas salían del servidor de demostración de OSRM. Los dos son servicios de buena
voluntad: sin garantía, pueden bloquear sin aviso, prohíben el uso intensivo y el de OSRM es solo para
uso no comercial y a una petición por segundo. Además se quiere usar el mapa en más partes del negocio
(geometría real de las carreteras, venta y encomiendas sobre el mapa, infografías), sin suscripciones
de pago y con control propio de los datos.

La región no es solo Guatemala: hay enclaves en Belice, Chetumal (México), San Salvador, San Pedro Sula,
Tegucigalpa y Puerto Cortés.

## Decisión

### Mapa base: un archivo PMTiles servido por Caddy

- Teselas vectoriales de [Protomaps](https://protomaps.com) (datos de OpenStreetMap), recortadas del
  build diario del planeta con `pmtiles extract` a la caja de la región (por defecto
  `-92.6,13.0,-86.8,18.8`, zoom 0–15). Es un solo archivo, `region-AAAAMMDD.pmtiles`, que el navegador
  lee por rangos de bytes: no hay servidor de teselas.
- Fuentes (Noto Sans regular, medium e italic) y sprites de `protomaps/basemaps-assets`.
- Caddy los sirve en `/mapas/` desde el volumen `mapas_data`. `mapa.json` (el manifiesto: qué archivo
  pedir y de cuándo son los datos) va sin caché; el `.pmtiles` lleva la fecha en el nombre y es inmutable;
  fuentes y sprites, una semana. Se conserva el archivo anterior para las pestañas abiertas.

### Rutas por carretera: Valhalla propio

- Contenedor `valhalla` (imagen `ghcr.io/valhalla/valhalla-scripted`) con el grafo construido desde un
  recorte de OpenStreetMap de la misma caja: los extractos de Geofabrik de Guatemala, Belice, El Salvador,
  Honduras y México, cada uno recortado con `osmium` y luego unidos. Solo lo llama PHP, por la red de Docker.
- `App\Mapa\Valhalla::recorrido(puntos)` pide `/route` con el perfil `bus` (respeta las vías reservadas a
  buses o prohibidas para ellos) y devuelve `Recorrido`: km, segundos y el trazado decodificado
  (`Polilinea`, seis decimales). Si Valhalla no responde, nulo: quien llama decide.
- `KilometrosCarretera` (editor de rutas y `app:ruta:kilometros`) lo usa en lugar de OSRM. La URL sale de
  `VALHALLA_URL` (por defecto `http://valhalla:8002`).

### Frontend: MapLibre GL en `shared/mapa`

- MapLibre GL JS (WebGL 2) en lugar de Leaflet. `useMapa(elemento, alListo, opciones)` arma el mapa con lo
  común: el estilo base (`estilo.ts`, capas de `@protomaps/basemaps` en español con prefijo `base-`), el
  tema claro u oscuro de la app (un `MutationObserver` sobre la clase de `<html>`; el cambio de tema
  reemplaza solo las capas `base-` y conserva lo dibujado, `combinarEstilo`), controles en español,
  sin rotar ni inclinar y `resize()` cuando cambia el contenedor.
- Cada pantalla dibuja con fuentes GeoJSON y capas propias (`capas.ts`: `fijarDatos`, `asegurarCapas`,
  `encuadrar`, `alPulsar`, `globoAlPasar`), con los colores como propiedades de cada elemento. Lo que es
  HTML va como marcador del DOM: los chips con el nombre (`Etiquetas`, sin depender de las fuentes del
  mapa base ni esconderse al tocarse), los buses (`marcador.ts`, un botón que se repinta sin rehacerse)
  y los indicadores de tarifas.
- Los colores del tema llegan como `oklch()`; `colorParaMapa` los pasa a `rgba()`, que es lo que entiende
  MapLibre.
- Sin manifiesto (los mapas no se prepararon) el mapa sale con fondo liso y un aviso en la esquina; todo
  lo demás funciona. Sin WebGL 2, un mensaje en lugar del mapa.
- Los niveles de zoom de MapLibre son uno menos que los de Leaflet para la misma escala: los `maxZoom` de
  las pantallas se bajaron uno.

### Operación

- `make mapas` construye la imagen de `mapas/` (go-pmtiles + osmium), corre `mapas/preparar.sh` y recrea
  Valhalla para que reconstruya su grafo (varios minutos; mientras tanto los km por carretera no se
  calculan). La primera vez y luego una vez al mes, por ejemplo con cron en la VPS:
  `0 4 1 * * cd /ruta/al/repo && make mapas`. La caja y los extractos se cambian con `MAPAS_BBOX`,
  `MAPAS_EXTRACTOS` y `MAPAS_MAXZOOM`.
- `mapas` y `valhalla` están en el perfil `mapas` de compose: `make up`/`make prod` no los levantan (sin
  datos no tienen qué servir). `make valhalla` lo vuelve a levantar tras un `make down`. Dev y prod
  comparten los volúmenes `mapas_data` y `valhalla_data`.

## Consecuencias

- El navegador ya no pide nada a terceros para el mapa, y el backend no depende de un servidor público
  para los km. Los datos se actualizan cuando se corre `make mapas`, no solos.
- Créditos en el mapa: Protomaps y OpenStreetMap (ODbL).
- La geometría real de las carreteras ya está disponible (`Recorrido::trazado`); guardarla por par de
  enclaves y dibujarla en lugar de las líneas rectas y arcos es el paso siguiente.
- `app:enclave:geocodificar` (ADR-025) sigue usando Nominatim público: es una tarea ocasional de a una
  petición por segundo, dentro de su política de uso.
- Dependencias: `maplibre-gl`, `pmtiles`, `@protomaps/basemaps` (fuera `leaflet` y `@types/leaflet`). El
  trozo de MapLibre se baja solo en las pantallas con mapa.
