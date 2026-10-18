/**
 * Gestos táctiles del chat en el teléfono, como en una app de mensajería:
 * deslizar un mensaje a la derecha lo responde, mantenerlo pulsado abre sus
 * acciones y, con la app instalada, deslizar desde el borde izquierdo vuelve
 * a la bandeja. Solo dedos (`pointerType === 'touch'`): el ratón no cambia.
 * Mueven el DOM directamente (sin estado reactivo) para seguir al dedo sin
 * re-renderizar la conversación. Reglas puras en `core/chat/gestos.ts`.
 */
import { onBeforeUnmount, watch, type Ref } from 'vue'
import { BORDE, PULSACION_LARGA, desplazamientoRespuesta, ejeDe, respondeAl, vuelveAlSoltar, type Eje } from '@/core/chat/gestos'

const vibrar = () => navigator.vibrate?.(12)

/** Devuelve el elemento a su sitio con una transición corta (o en seco, si se pide menos movimiento). */
function devolver(el: HTMLElement) {
  const suave = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  el.style.transition = suave ? 'transform 0.2s ease-out' : ''
  el.style.transform = ''
  el.style.removeProperty('--arrastre')
  if (suave) el.addEventListener('transitionend', () => (el.style.transition = ''), { once: true })
}

/** Escucha en `raiz` mientras exista; se suelta al desmontar. */
function escuchar(raiz: Ref<HTMLElement | null>, eventos: Partial<Record<keyof HTMLElementEventMap, (e: never) => void>>) {
  const pares = Object.entries(eventos) as [string, EventListener][]
  const parar = watch(
    raiz,
    (el, previo) => {
      pares.forEach(([tipo, fn]) => previo?.removeEventListener(tipo, fn, true))
      pares.forEach(([tipo, fn]) => el?.addEventListener(tipo, fn, { capture: true, passive: tipo !== 'click' && tipo !== 'contextmenu' }))
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    parar()
    pares.forEach(([tipo, fn]) => raiz.value?.removeEventListener(tipo, fn, true))
  })
}

/**
 * Sobre los mensajes de `lienzo` (cada uno `.mensaje[data-id]`, con
 * `touch-action: pan-y` para que el desplazamiento vertical siga siendo
 * del navegador).
 */
export function useGestosMensajes(lienzo: Ref<HTMLElement | null>, al: { responder: (id: number) => void; acciones: (id: number) => void; puedeResponder: () => boolean }) {
  let toque: { id: number; el: HTMLElement; x: number; y: number; eje: Eje | null; dx: number; avisado: boolean; temporizador: ReturnType<typeof setTimeout> } | null = null
  /** El clic que sigue a un gesto no es un clic (no abre enlaces ni citas). */
  let tragarClic = false

  function terminar() {
    if (!toque) return
    clearTimeout(toque.temporizador)
    devolver(toque.el)
    toque = null
  }

  function inicio(e: PointerEvent) {
    if (e.pointerType !== 'touch' || !e.isPrimary) return
    const el = (e.target as Element | null)?.closest<HTMLElement>('.mensaje[data-id]')
    if (!el) return
    tragarClic = false
    const id = Number(el.dataset.id)
    toque = {
      id, el, x: e.clientX, y: e.clientY, eje: null, dx: 0, avisado: false,
      temporizador: setTimeout(() => {
        if (!toque || toque.eje) return
        terminar()
        tragarClic = true
        vibrar()
        al.acciones(id)
      }, PULSACION_LARGA),
    }
  }

  function mover(e: PointerEvent) {
    if (!toque || e.pointerType !== 'touch') return
    const dx = e.clientX - toque.x
    toque.eje ??= ejeDe(dx, e.clientY - toque.y)
    if (!toque.eje) return
    clearTimeout(toque.temporizador)
    // Vertical: es desplazar la conversación, del navegador.
    if (toque.eje === 'y' || !al.puedeResponder()) return terminar()
    tragarClic = true
    toque.dx = dx
    const d = desplazamientoRespuesta(dx)
    toque.el.style.transition = ''
    toque.el.style.transform = `translateX(${d}px)`
    toque.el.style.setProperty('--arrastre', String(Math.min(1, d / 64)))
    if (respondeAl(dx) && !toque.avisado) {
      toque.avisado = true
      vibrar()
    }
  }

  function soltar() {
    if (!toque) return
    const { id, eje, dx } = toque
    terminar()
    if (eje === 'x' && respondeAl(dx) && al.puedeResponder()) al.responder(id)
  }

  function clic(e: MouseEvent) {
    if (!tragarClic) return
    tragarClic = false
    e.preventDefault()
    e.stopPropagation()
  }

  // El menú del sistema al mantener pulsado (Android) lo reemplaza el propio.
  function menu(e: MouseEvent) {
    if ((e as PointerEvent).pointerType === 'touch' || toque || tragarClic) e.preventDefault()
  }

  escuchar(lienzo, { pointerdown: inicio, pointermove: mover, pointerup: soltar, pointercancel: terminar, click: clic, contextmenu: menu })
}

/**
 * Deslizar desde el borde izquierdo de `raiz` para volver: la vista sigue al
 * dedo y, al soltar lejos o rápido, sale por la derecha y llama a `volver`.
 * Solo con `activo` (la app instalada en el teléfono: en el navegador ese
 * gesto ya es suyo y se haría dos veces).
 */
export function useVolverDesdeElBorde(raiz: Ref<HTMLElement | null>, activo: () => boolean, volver: () => void) {
  let toque: { x: number; y: number; t: number; eje: Eje | null; dx: number } | null = null

  function inicio(e: PointerEvent) {
    const el = raiz.value
    if (!el || e.pointerType !== 'touch' || !e.isPrimary || !activo()) return
    if (e.clientX - el.getBoundingClientRect().left > BORDE) return
    toque = { x: e.clientX, y: e.clientY, t: e.timeStamp, eje: null, dx: 0 }
  }

  function mover(e: PointerEvent) {
    const el = raiz.value
    if (!toque || !el) return
    const dx = e.clientX - toque.x
    toque.eje ??= ejeDe(dx, e.clientY - toque.y)
    if (toque.eje === 'y') {
      toque = null
      return
    }
    if (toque.eje !== 'x') return
    toque.dx = Math.max(0, dx)
    el.style.transition = ''
    el.style.transform = `translateX(${toque.dx}px)`
  }

  function soltar(e: PointerEvent) {
    const el = raiz.value
    if (!toque || !el) return
    const { dx, t, eje } = toque
    toque = null
    if (eje !== 'x') return
    if (!vuelveAlSoltar(dx, el.clientWidth, e.timeStamp - t)) return devolver(el)
    el.style.transition = 'transform 0.18s ease-out'
    el.style.transform = 'translateX(100%)'
    setTimeout(volver, 180)
  }

  function cancelar() {
    if (toque && raiz.value) devolver(raiz.value)
    toque = null
  }

  escuchar(raiz, { pointerdown: inicio, pointermove: mover, pointerup: soltar, pointercancel: cancelar })
}

/** La app abierta como instalada (pantalla de inicio), no en una pestaña del navegador. */
export const appInstalada = () =>
  window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true
