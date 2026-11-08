/**
 * Ayudas para dibujar sobre un mapa de MapLibre (ADR-055). Cada pantalla tiene
 * sus fuentes GeoJSON y sus capas; estas funciones son idempotentes para que el
 * mismo «dibujar» sirva la primera vez y en cada cambio de datos.
 */
import type { FeatureCollection } from 'geojson'
import type { AddLayerObject, GeoJSONSource, Map as MapaLibre, MapLayerMouseEvent, Marker } from 'maplibre-gl'
import { aLngLat, limites, type LatLng } from './geo'
import { maplibregl } from './maplibre'

type Propiedades = Record<string, unknown>

/** Pone los datos de una fuente GeoJSON (la crea la primera vez). */
export function fijarDatos(mapa: MapaLibre, id: string, datos: FeatureCollection): void {
  const fuente = mapa.getSource<GeoJSONSource>(id)
  if (fuente) fuente.setData(datos)
  else mapa.addSource(id, { type: 'geojson', data: datos })
}

/** Agrega las capas que falten, en el orden dado (cada una encima de la anterior). */
export function asegurarCapas(mapa: MapaLibre, capas: AddLayerObject[]): void {
  for (const capa of capas) if (!mapa.getLayer(capa.id)) mapa.addLayer(capa)
}

/**
 * Encuadra los puntos. `maxZoom` en niveles de MapLibre (uno menos que los de Leaflet
 * para la misma escala). El margen se achica en un mapa chico: si no cupiera, no se movería.
 */
export function encuadrar(mapa: MapaLibre, puntos: LatLng[], { margen = 40, maxZoom = 10, animar = true }: { margen?: number; maxZoom?: number; animar?: boolean } = {}): void {
  const caja = limites(puntos)
  if (!caja) return
  const { clientWidth: ancho, clientHeight: alto } = mapa.getContainer()
  const padding = Math.max(0, Math.min(margen, Math.floor(ancho / 4), Math.floor(alto / 4)))
  mapa.fitBounds(caja, { padding, maxZoom, duration: animar ? 500 : 0 })
}

/** Lo que hace una capa al pasar el cursor o pulsarla: la mano como cursor sobre lo pulsable. */
function cursor(mapa: MapaLibre, capa: string): void {
  mapa.on('mouseenter', capa, () => (mapa.getCanvas().style.cursor = 'pointer'))
  mapa.on('mouseleave', capa, () => (mapa.getCanvas().style.cursor = ''))
}

/** Clic (o toque) en un elemento de la capa: recibe sus propiedades. */
export function alPulsar(mapa: MapaLibre, capa: string, accion: (propiedades: Propiedades) => void): void {
  cursor(mapa, capa)
  mapa.on('click', capa, (e: MapLayerMouseEvent) => {
    const f = e.features?.[0]
    if (f) accion(f.properties ?? {})
  })
}

/**
 * Globo con texto al pasar el cursor sobre un punto de la capa (y al tocarlo, en
 * pantallas táctiles). `html` recibe las propiedades y devuelve HTML ya escapado, o
 * nulo para no mostrar nada.
 */
export function globoAlPasar(mapa: MapaLibre, capa: string, html: (propiedades: Propiedades) => string | null, clase = 'mapa-globo'): void {
  const globo = new maplibregl.Popup({ closeButton: false, closeOnClick: false, className: clase, offset: 10, maxWidth: '18rem' })
  const mostrar = (e: MapLayerMouseEvent) => {
    const f = e.features?.[0]
    const contenido = f ? html(f.properties ?? {}) : null
    if (!f || contenido === null) return void globo.remove()
    const en = f.geometry.type === 'Point' ? (f.geometry.coordinates as [number, number]) : e.lngLat
    globo.setLngLat(en).setHTML(contenido).addTo(mapa)
  }
  mapa.on('mousemove', capa, mostrar)
  mapa.on('click', capa, mostrar)
  mapa.on('mouseleave', capa, () => globo.remove())
}

export interface Etiqueta {
  clave: string
  en: LatLng
  texto: string
}

/**
 * Etiquetas fijas junto a un punto (el chip con el nombre del lugar), como
 * elementos del DOM sobre el mapa: no dependen de las fuentes del mapa base y no
 * se esconden cuando se tocan. No reciben el puntero: el clic llega al punto.
 */
export class Etiquetas {
  private readonly marcas = new Map<string, Marker>()

  constructor(private readonly mapa: MapaLibre) {}

  fijar(lista: Etiqueta[]): void {
    const vigentes = new Set<string>()
    for (const e of lista) {
      vigentes.add(e.clave)
      let marca = this.marcas.get(e.clave)
      if (!marca) {
        const el = document.createElement('div')
        el.className = 'mapa-chip'
        el.setAttribute('aria-hidden', 'true')
        marca = new maplibregl.Marker({ element: el, anchor: 'left', offset: [8, 0] }).setLngLat(aLngLat(e.en)).addTo(this.mapa)
        this.marcas.set(e.clave, marca)
      } else marca.setLngLat(aLngLat(e.en))
      const el = marca.getElement()
      if (el.textContent !== e.texto) el.textContent = e.texto
    }
    for (const [clave, marca] of this.marcas) {
      if (vigentes.has(clave)) continue
      marca.remove()
      this.marcas.delete(clave)
    }
  }

  limpiar(): void {
    this.fijar([])
  }
}

const PRIMARIO_POR_DEFECTO = '#3b82f6'
const colores = new Map<string, string>()

/**
 * Un color CSS cualquiera como `rgba()`: MapLibre no entiende `oklch()` ni
 * `color-mix()`, que es como llegan los del tema. Lo pinta el navegador en un
 * lienzo de un píxel y se lee el resultado. Nulo si el navegador no lo reconoce.
 */
export function colorParaMapa(valor: string): string | null {
  const conocido = colores.get(valor)
  if (conocido) return conocido
  const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true })
  if (!ctx) return null
  // Un color que no es válido deja el anterior: así se nota.
  const centinela = '#010203'
  ctx.fillStyle = centinela
  ctx.fillStyle = valor
  if (ctx.fillStyle === centinela && valor.trim().toLowerCase() !== centinela) return null
  ctx.fillRect(0, 0, 1, 1)
  const [r = 0, g = 0, b = 0, a = 255] = ctx.getImageData(0, 0, 1, 1).data
  const rgba = `rgba(${r}, ${g}, ${b}, ${Math.round((a / 255) * 1000) / 1000})`
  colores.set(valor, rgba)
  return rgba
}

/** Color primario del tema en el elemento, en un formato que MapLibre entiende. */
export function colorPrimario(el: Element | null): string {
  const valor = el ? getComputedStyle(el).getPropertyValue('--p-primary-color').trim() : ''
  return (valor && colorParaMapa(valor)) || PRIMARIO_POR_DEFECTO
}

/** Gris del borde de un lugar que no es parada, y el relleno blanco de los puntos (en ambos temas). */
export const GRIS_LUGAR = '#64748b'
export const BLANCO = '#ffffff'
