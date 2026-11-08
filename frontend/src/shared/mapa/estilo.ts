/**
 * Estilo del mapa base (ADR-055), puro: las capas de Protomaps con la apariencia
 * del mapa estándar de OpenStreetMap (`sabor.ts`) sobre las teselas que sirve
 * Caddy en `/mapas/` y, si todavía no se prepararon (`make mapas`), un
 * fondo liso. Las capas del mapa base llevan el prefijo `base-` y la fuente se
 * llama `base`: así un cambio de tema reemplaza solo el mapa base y deja encima
 * lo que dibujó cada pantalla (`combinarEstilo`).
 */
import { layers } from '@protomaps/basemaps'
import type { LayerSpecification, StyleSpecification } from 'maplibre-gl'
import { SABOR_OSM, colorearVias, oscurecer } from './sabor'

/** `/mapas/mapa.json`: qué archivo de teselas pedir y de cuándo son los datos (lo escribe `mapas/preparar.sh`). */
export interface ManifiestoMapa {
  teselas: string
  datos: string
  osm?: string
  /** [oeste, sur, este, norte] */
  bbox?: [number, number, number, number]
  preparado?: string
}

export type TemaMapa = 'light' | 'dark'

export const FUENTE_BASE = 'base'
export const PREFIJO_BASE = 'base-'
export const ATRIBUCION =
  '<a href="https://protomaps.com" target="_blank" rel="noopener">Protomaps</a> © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'

/** Lo mínimo para usar el manifiesto: un nombre de archivo de teselas sin rutas. */
export function esManifiesto(x: unknown): x is ManifiestoMapa {
  if (typeof x !== 'object' || x === null) return false
  const teselas = (x as { teselas?: unknown }).teselas
  return typeof teselas === 'string' && /^[\w.-]+\.pmtiles$/.test(teselas)
}

/** Estilo del mapa base en el tema dado; `origen` es el del sitio (las URLs del estilo deben ser absolutas). */
export function estiloBase(manifiesto: ManifiestoMapa | null, tema: TemaMapa, origen: string): StyleSpecification {
  const enTema = (capas: LayerSpecification[]) => (tema === 'dark' ? oscurecer(capas) : capas)
  if (!manifiesto) {
    return { version: 8, sources: {}, layers: enTema([{ id: `${PREFIJO_BASE}fondo`, type: 'background', paint: { 'background-color': SABOR_OSM.earth } }]) }
  }
  return {
    version: 8,
    glyphs: `${origen}/mapas/fuentes/{fontstack}/{range}.pbf`,
    sprite: `${origen}/mapas/sprites/v4/${tema}`,
    sources: {
      [FUENTE_BASE]: { type: 'vector', url: `pmtiles://${origen}/mapas/${manifiesto.teselas}`, attribution: ATRIBUCION },
    },
    layers: enTema(colorearVias(layers(FUENTE_BASE, SABOR_OSM, { lang: 'es' }))).map((capa) => ({ ...capa, id: PREFIJO_BASE + capa.id })),
  }
}

/**
 * Cambio de tema (`setStyle(nuevo, { transformStyle })`): el mapa base nuevo y,
 * encima y en el mismo orden, las fuentes y capas que había agregado la pantalla.
 */
export function combinarEstilo(anterior: StyleSpecification | undefined, nuevo: StyleSpecification): StyleSpecification {
  if (!anterior) return nuevo
  const sources = { ...nuevo.sources }
  for (const [id, fuente] of Object.entries(anterior.sources)) if (id !== FUENTE_BASE) sources[id] = fuente
  return { ...nuevo, sources, layers: [...nuevo.layers, ...anterior.layers.filter((capa) => !capa.id.startsWith(PREFIJO_BASE))] }
}
