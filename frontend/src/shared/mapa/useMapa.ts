/**
 * Un mapa de MapLibre en el elemento dado (ADR-055), con lo que comparten todas
 * las pantallas: el mapa base propio (teselas en `/mapas/`, o fondo liso si no se
 * prepararon), el tema claro u oscuro de la app (cambia con ella sin perder lo
 * dibujado), controles en español, sin rotar ni inclinar, y el tamaño al día
 * cuando cambia el contenedor (sidebars, maximizar).
 *
 * `alListo` corre cuando el mapa puede recibir fuentes y capas; antes de eso
 * `listo` es falso y las pantallas solo guardan sus datos.
 */
import type { Map as MapaLibre } from 'maplibre-gl'
import { onBeforeUnmount, onMounted, ref, shallowRef, type Ref } from 'vue'
import { combinarEstilo, estiloBase, type ManifiestoMapa, type TemaMapa } from './estilo'
import { LOCALE_ES } from './locale'
import { cargarManifiesto } from './manifiesto'
import { maplibregl } from './maplibre'

/** Centro aproximado de Guatemala y zoom que la muestra entera (zoom de MapLibre: uno menos que en Leaflet). */
const CENTRO: [number, number] = [-90.25, 15.45]
const ZOOM_INICIAL = 6

export interface OpcionesMapa {
  /** Flechas y +/− del teclado (por defecto sí). Apagado, pulsar el mapa no le da el foco. */
  teclado?: boolean
}

const temaDeLaApp = (): TemaMapa => (document.documentElement.classList.contains('dark') ? 'dark' : 'light')

/** Aviso en una esquina: los mapas no se prepararon en el servidor. */
class AvisoSinFondo {
  private el: HTMLElement | null = null
  onAdd(): HTMLElement {
    this.el = document.createElement('div')
    this.el.className = 'maplibregl-ctrl mapa-aviso'
    this.el.textContent = 'Sin mapa base: falta prepararlo en el servidor (make mapas).'
    return this.el
  }
  onRemove(): void {
    this.el?.remove()
  }
}

export function useMapa(contenedor: Ref<HTMLElement | null>, alListo: (mapa: MapaLibre) => void, opciones: OpcionesMapa = {}) {
  const mapa = shallowRef<MapaLibre | null>(null)
  const listo = ref(false)
  let manifiesto: ManifiestoMapa | null = null
  let tema: TemaMapa = 'light'
  let vivo = true
  let observadorTamano: ResizeObserver | undefined
  let observadorTema: MutationObserver | undefined

  /** El mapa base del tema nuevo; lo dibujado por la pantalla queda encima. */
  function cambiarTema() {
    const m = mapa.value
    const nuevo = temaDeLaApp()
    if (!m || nuevo === tema) return
    // Mientras carga el primer estilo no hay qué combinar: se cambia al terminar.
    if (!m.getStyle()) return void m.once('load', cambiarTema)
    tema = nuevo
    m.setStyle(estiloBase(manifiesto, tema, location.origin), { transformStyle: combinarEstilo })
  }

  function avisarSinWebgl(el: HTMLElement) {
    const aviso = document.createElement('p')
    aviso.className = 'mapa-sin-webgl'
    aviso.textContent = 'Este navegador no puede mostrar el mapa (necesita WebGL 2). Pruebe con otro navegador o active la aceleración por hardware.'
    el.append(aviso)
  }

  onMounted(async () => {
    manifiesto = await cargarManifiesto()
    const el = contenedor.value
    if (!vivo || !el) return
    tema = temaDeLaApp()
    let m: MapaLibre
    try {
      m = new maplibregl.Map({
        container: el,
        style: estiloBase(manifiesto, tema, location.origin),
        center: CENTRO,
        zoom: ZOOM_INICIAL,
        locale: LOCALE_ES,
        keyboard: opciones.teclado ?? true,
        attributionControl: { compact: true },
        // Mapa plano y con el norte arriba, como siempre: sin rotar ni inclinar.
        dragRotate: false,
        pitchWithRotate: false,
        touchPitch: false,
        maxPitch: 0,
        renderWorldCopies: false,
      })
    } catch {
      avisarSinWebgl(el)
      return
    }
    m.touchZoomRotate.disableRotation()
    m.keyboard.disableRotation()
    m.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left')
    if (!manifiesto) m.addControl(new AvisoSinFondo(), 'bottom-left')
    mapa.value = m
    m.once('load', () => {
      if (!vivo) return
      listo.value = true
      alListo(m)
    })
    // El contenedor cambia de tamaño al abrir o cerrar sidebars, al maximizar o con el breakpoint.
    observadorTamano = new ResizeObserver(() => mapa.value?.resize())
    observadorTamano.observe(el)
    observadorTema = new MutationObserver(cambiarTema)
    observadorTema.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
  })

  onBeforeUnmount(() => {
    vivo = false
    observadorTamano?.disconnect()
    observadorTema?.disconnect()
    mapa.value?.remove()
    mapa.value = null
    listo.value = false
  })

  return { mapa, listo }
}
