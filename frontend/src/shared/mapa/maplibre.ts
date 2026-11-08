/**
 * MapLibre listo para usar (ADR-055): el worker que Vite copia a los assets y el
 * protocolo `pmtiles://` que lee las teselas del mapa base por rangos de bytes.
 * Las pantallas importan `maplibregl` desde aquí, no desde el paquete.
 */
import * as maplibregl from 'maplibre-gl'
import urlTrabajador from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url'
import 'maplibre-gl/dist/maplibre-gl.css'
import { Protocol } from 'pmtiles'
import './mapa.css'

maplibregl.setWorkerUrl(urlTrabajador)
maplibregl.addProtocol('pmtiles', new Protocol().tile)

export { maplibregl }
