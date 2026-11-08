/**
 * GeoJSON para las capas de los mapas (puro). El resto del código guarda los puntos
 * como [latitud, longitud]; MapLibre y GeoJSON los quieren al revés: aquí se voltean.
 */
import type { Feature, FeatureCollection, LineString, Point } from 'geojson'

/** [latitud, longitud] en grados. */
export type LatLng = [number, number]
type Propiedades = Record<string, unknown>

export const aLngLat = ([lat, lng]: LatLng): [number, number] => [lng, lat]

export function coleccion<G extends Point | LineString>(features: Feature<G>[]): FeatureCollection<G> {
  return { type: 'FeatureCollection', features }
}

export function punto(p: LatLng, properties: Propiedades = {}): Feature<Point> {
  return { type: 'Feature', geometry: { type: 'Point', coordinates: aLngLat(p) }, properties }
}

/** Una línea por los puntos en orden; con menos de dos no hay línea. */
export function linea(puntos: LatLng[], properties: Propiedades = {}): Feature<LineString> | null {
  if (puntos.length < 2) return null
  return { type: 'Feature', geometry: { type: 'LineString', coordinates: puntos.map(aLngLat) }, properties }
}

/** Las líneas que sí lo son (descarta las de menos de dos puntos). */
export function lineas(lista: Array<Feature<LineString> | null>): FeatureCollection<LineString> {
  return coleccion(lista.filter((l): l is Feature<LineString> => l !== null))
}

/** Caja [[oeste, sur], [este, norte]] que contiene los puntos; nula sin puntos. */
export function limites(puntos: LatLng[]): [[number, number], [number, number]] | null {
  if (puntos.length === 0) return null
  let sur = Infinity
  let norte = -Infinity
  let oeste = Infinity
  let este = -Infinity
  for (const [lat, lng] of puntos) {
    sur = Math.min(sur, lat)
    norte = Math.max(norte, lat)
    oeste = Math.min(oeste, lng)
    este = Math.max(este, lng)
  }
  return [
    [oeste, sur],
    [este, norte],
  ]
}

/**
 * Si `p` cae dentro de la caja [[oeste, sur], [este, norte]] recortada en `margen`
 * (fracción de su ancho y alto por cada lado): «seguir al bus» mueve el mapa cuando se sale.
 */
export function dentroDe(caja: [[number, number], [number, number]], p: LatLng, margen = 0): boolean {
  const [[oeste, sur], [este, norte]] = caja
  const dx = (este - oeste) * margen
  const dy = (norte - sur) * margen
  return p[0] >= sur + dy && p[0] <= norte - dy && p[1] >= oeste + dx && p[1] <= este - dx
}
