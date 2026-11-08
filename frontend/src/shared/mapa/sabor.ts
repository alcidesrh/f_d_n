/**
 * Apariencia del mapa base (ADR-055): la del mapa estándar de OpenStreetMap que
 * se usaba con Leaflet, sobre las teselas vectoriales de Protomaps.
 *
 * - `SABOR_OSM`: los colores de OpenStreetMap Carto (tierra crema, agua celeste,
 *   bosques y parques verdes, límites morados) en lugar del gris de Protomaps.
 * - `colorearVias`: las carreteras por categoría, como en Carto (autopista rosa,
 *   troncal salmón, primaria naranja, secundaria amarilla, el resto blancas con
 *   borde gris). Protomaps las agrupa (`highway`, `major_road`); la categoría de
 *   OpenStreetMap llega en `kind_detail`.
 * - `oscurecer`: el tema oscuro es el mismo de antes, el claro pasado por el filtro
 *   que Leaflet aplicaba a sus mosaicos, `invert(1) hue-rotate(180deg)
 *   brightness(0.95) contrast(0.9)`, calculado sobre cada color del estilo.
 */
import { LIGHT, type Flavor } from '@protomaps/basemaps'
import type { LayerSpecification } from 'maplibre-gl'

const TIERRA = '#f2efe9'
const AGUA = '#aad3df'
const ETIQUETA_AGUA = '#4d80b3'
const BOSQUE = '#add19e'
const PARQUE = '#c8facc'
const GRIS_BORDE = '#bbbbbb'

export const SABOR_OSM: Flavor = {
  ...LIGHT,
  background: TIERRA,
  earth: TIERRA,
  park_a: PARQUE,
  park_b: PARQUE,
  hospital: '#ffffe5',
  industrial: '#ebdbe8',
  school: '#ffffe5',
  wood_a: BOSQUE,
  wood_b: BOSQUE,
  pedestrian: '#dddde8',
  scrub_a: '#c8d7ab',
  scrub_b: '#c8d7ab',
  glacier: '#ddecec',
  sand: '#f5e9c6',
  beach: '#fff1ba',
  aerodrome: '#e9e7e2',
  runway: '#bbbbcc',
  water: AGUA,
  zoo: '#c8facc',
  military: '#f3e3dd',
  tunnel_other_casing: GRIS_BORDE,
  tunnel_minor_casing: GRIS_BORDE,
  tunnel_link_casing: GRIS_BORDE,
  tunnel_major_casing: GRIS_BORDE,
  tunnel_highway_casing: GRIS_BORDE,
  tunnel_other: '#f5f5f5',
  tunnel_minor: '#f5f5f5',
  tunnel_link: '#f5f5f5',
  tunnel_major: '#f5f5f5',
  tunnel_highway: '#f5f5f5',
  pier: '#f2efe9',
  buildings: '#d9d0c9',
  minor_service_casing: GRIS_BORDE,
  minor_casing: GRIS_BORDE,
  link_casing: GRIS_BORDE,
  major_casing_late: '#8f8f8f',
  highway_casing_late: '#dc2a67',
  other: '#fa8072',
  minor_service: '#ffffff',
  minor_a: '#ffffff',
  minor_b: '#ffffff',
  link: '#ffffff',
  major_casing_early: '#8f8f8f',
  major: '#ffffff',
  highway_casing_early: '#dc2a67',
  highway: '#e892a2',
  railway: '#707070',
  boundaries: '#8d618b',
  bridges_other_casing: GRIS_BORDE,
  bridges_minor_casing: GRIS_BORDE,
  bridges_link_casing: GRIS_BORDE,
  bridges_major_casing: '#8f8f8f',
  bridges_highway_casing: '#dc2a67',
  bridges_other: '#fa8072',
  bridges_minor: '#ffffff',
  bridges_link: '#ffffff',
  bridges_major: '#ffffff',
  bridges_highway: '#e892a2',
  roads_label_minor: '#333333',
  roads_label_minor_halo: '#ffffff',
  roads_label_major: '#333333',
  roads_label_major_halo: '#ffffff',
  ocean_label: ETIQUETA_AGUA,
  subplace_label: '#555555',
  subplace_label_halo: '#ffffff',
  city_label: '#222222',
  city_label_halo: '#ffffff',
  state_label: '#8d618b',
  state_label_halo: '#ffffff',
  country_label: '#8d618b',
  address_label: '#666666',
  address_label_halo: '#ffffff',
  landcover: {
    // A poca escala (zoom < 7), suave como en Carto: ahí casi todo es tierra.
    barren: '#eee5dc',
    farmland: '#eef0d5',
    forest: '#d7e6c8',
    glacier: '#ddecec',
    grassland: '#e3eed5',
    scrub: '#e3e9d2',
    urban_area: '#e0dfdf',
  },
}

/** Relleno y borde de cada categoría de carretera de OpenStreetMap (`kind_detail`), los de Carto. */
const VIAS: Record<string, [relleno: string, borde: string]> = {
  motorway: ['#e892a2', '#dc2a67'],
  trunk: ['#f9b29c', '#c84e2f'],
  primary: ['#fcd6a4', '#a06b00'],
  secondary: ['#f7fabf', '#707d05'],
  tertiary: ['#ffffff', '#8f8f8f'],
}

/** Capas de autopistas y vías principales (normales, puentes y túneles; relleno o borde). */
const CAPA_VIA = /^roads_(?:bridges_|tunnels_)?(highway|major)(_casing(?:_early|_late)?)?$/

function porCategoria(parte: 0 | 1, otro: string): unknown {
  return ['match', ['get', 'kind_detail'], ...Object.entries(VIAS).flatMap(([via, colores]) => [via, colores[parte]]), otro]
}

/**
 * Ancho de las vías principales, el de Carto: se ven desde la vista de todo el país
 * (Protomaps las deja de un píxel hasta el zoom 12). El borde va por fuera
 * (`line-gap-width`), así que relleno y borde comparten la medida.
 */
const ANCHO_VIA = ['interpolate', ['exponential', 1.6], ['zoom'], 5, 0.6, 8, 1.6, 10, 3, 12, 5, 15, 9, 18, 18]

/** Pinta las carreteras principales por categoría y con el ancho de Carto (las capas son las de `layers()` de Protomaps, sin prefijo). */
export function colorearVias(capas: LayerSpecification[], sabor: Flavor = SABOR_OSM): LayerSpecification[] {
  return capas.map((capa) => {
    const m = CAPA_VIA.exec(capa.id)
    if (!m || capa.type !== 'line') return capa
    const borde = m[2] !== undefined
    const otro = m[1] === 'highway' ? (borde ? sabor.highway_casing_early : sabor.highway) : borde ? sabor.major_casing_early : sabor.major
    const ancho = borde ? { 'line-gap-width': ANCHO_VIA } : { 'line-width': ANCHO_VIA }
    return { ...capa, paint: { ...capa.paint, ...ancho, 'line-color': porCategoria(borde ? 1 : 0, otro) } } as LayerSpecification
  })
}

type Rgba = [number, number, number, number]

/** `#rgb`, `#rrggbb`, `#rrggbbaa`, `rgb()` y `rgba()`: los formatos de los colores de Protomaps y de este archivo. */
export function leerColor(texto: string): Rgba | null {
  const t = texto.trim().toLowerCase()
  let m = /^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/.exec(t)
  if (m) {
    let h = m[1]!
    if (h.length === 3) h = [...h].map((c) => c + c).join('')
    const n = (i: number) => parseInt(h.slice(i, i + 2), 16)
    return [n(0), n(2), n(4), h.length === 8 ? n(6) / 255 : 1]
  }
  m = /^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/.exec(t)
  if (m) return [Number(m[1]), Number(m[2]), Number(m[3]), m[4] === undefined ? 1 : Number(m[4])]
  return null
}

const limitar = (x: number) => Math.min(1, Math.max(0, x))

/**
 * El filtro de los mosaicos oscuros de Leaflet sobre un color, paso a paso como lo
 * aplica el navegador: invertir, girar el tono 180° (matriz de la especificación
 * de Filter Effects), 95 % de brillo y 90 % de contraste. Lo que no es un color,
 * igual.
 */
export function filtroOscuro(color: string): string {
  const c = leerColor(color)
  if (!c) return color
  const [r, g, b] = c.slice(0, 3).map((x) => 1 - x / 255) as [number, number, number]
  const girado = [
    -0.574 * r + 1.43 * g + 0.144 * b,
    0.426 * r + 0.43 * g + 0.144 * b,
    0.426 * r + 1.43 * g - 0.856 * b,
  ].map(limitar)
  const final = girado.map((x) => Math.round(limitar((limitar(x * 0.95) - 0.5) * 0.9 + 0.5) * 255))
  return `rgba(${final.join(', ')}, ${c[3]})`
}

function oscurecerValor(valor: unknown): unknown {
  if (typeof valor === 'string') return filtroOscuro(valor)
  if (Array.isArray(valor)) return valor.map(oscurecerValor)
  return valor
}

/** Las capas en el tema oscuro: cada color de su `paint` pasado por `filtroOscuro`. */
export function oscurecer(capas: LayerSpecification[]): LayerSpecification[] {
  return capas.map((capa) => {
    if (!capa.paint) return capa
    const paint = Object.fromEntries(Object.entries(capa.paint).map(([clave, valor]) => [clave, clave.endsWith('-color') ? oscurecerValor(valor) : valor]))
    return { ...capa, paint } as LayerSpecification
  })
}
