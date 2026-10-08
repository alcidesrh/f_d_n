/**
 * Geometría de la ventana del chat (ADR-026): dónde y de qué tamaño va en
 * cada modo y estado. Puro: el componente la anima (GSAP) y la persiste.
 *
 * - `centro`: es la página `/chat` (no usa esta geometría).
 * - `esquina`: abajo a la derecha; minimizada asoma solo la barra.
 * - `flotante`: donde el usuario la deje, del tamaño que le dé; minimizada se
 *   encoge a la barra en ese mismo lugar.
 *
 * Maximizada ocupa la pantalla. Debajo de `MOVIL` abierta también (no caben
 * dos cosas); minimizada sigue siendo la barra.
 */

export type ModoVentana = 'centro' | 'esquina' | 'flotante'

/** Rectángulo en px, relativo al viewport. */
export interface Caja {
  x: number
  y: number
  w: number
  h: number
}

export interface Pantalla {
  w: number
  h: number
}

export interface EstadoVentana {
  modo: ModoVentana
  minimizada: boolean
  maximizada: boolean
  /** La que dejó el usuario en modo flotante (null: aún no la movió). */
  flotante: Caja | null
}

/** Alto de la barra de título (lo que queda a la vista minimizada). */
export const BARRA = 44
export const MARGEN = 16
export const MINIMA = { w: 320, h: 380 } as const
export const ANCHO_MINIMIZADA = 280
/** Ancho de pantalla (px, `md`) debajo del cual abierta ocupa todo. */
export const MOVIL = 768

const ESQUINA = { w: 400, h: 640 } as const

const entre = (v: number, min: number, max: number) => Math.min(Math.max(v, min), Math.max(min, max))

/** La primera vez que flota: a la derecha, debajo del encabezado. */
export function flotanteInicial(p: Pantalla): Caja {
  const w = Math.min(420, p.w - 2 * MARGEN)
  const h = Math.min(600, p.h - 96)
  return encajar({ x: p.w - w - 2 * MARGEN, y: 72, w, h }, p)
}

/** Tamaño entre el mínimo y la pantalla, y entera dentro de la pantalla. */
export function encajar(c: Caja, p: Pantalla): Caja {
  const w = Math.round(entre(c.w, Math.min(MINIMA.w, p.w), p.w))
  const h = Math.round(entre(c.h, Math.min(MINIMA.h, p.h), p.h))
  return { x: Math.round(entre(c.x, 0, p.w - w)), y: Math.round(entre(c.y, 0, p.h - h)), w, h }
}

export function cajaDe(e: EstadoVentana, p: Pantalla): Caja {
  const completa = { x: 0, y: 0, w: p.w, h: p.h }
  if (!e.minimizada && (e.maximizada || p.w < MOVIL)) return completa

  if (e.modo === 'flotante') {
    const base = encajar(e.flotante ?? flotanteInicial(p), p)
    if (!e.minimizada) return base
    const w = Math.min(ANCHO_MINIMIZADA, p.w - 2 * MARGEN)
    return { x: Math.round(entre(base.x, 0, p.w - w)), y: Math.round(entre(base.y, 0, p.h - BARRA)), w, h: BARRA }
  }

  const w = Math.min(ESQUINA.w, p.w - 2 * MARGEN)
  if (e.minimizada) return { x: p.w - w - MARGEN, y: p.h - BARRA, w, h: BARRA }
  const h = Math.min(ESQUINA.h, p.h - 2 * MARGEN)
  return { x: p.w - w - MARGEN, y: p.h - h - MARGEN, w, h }
}

/** Bordes y esquinas por los que se redimensiona la flotante. */
export type Borde = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw'
export const BORDES: Borde[] = ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw']

/**
 * Arrastrar un borde `dx`/`dy` px desde la caja `inicio`: el borde opuesto
 * queda quieto, sin bajar del mínimo ni salir de la pantalla.
 */
export function redimensionar(inicio: Caja, borde: Borde, dx: number, dy: number, p: Pantalla): Caja {
  let { x, y, w, h } = inicio
  const minW = Math.min(MINIMA.w, p.w)
  const minH = Math.min(MINIMA.h, p.h)
  if (borde.includes('e')) w = entre(inicio.w + dx, minW, p.w - inicio.x)
  if (borde.includes('s')) h = entre(inicio.h + dy, minH, p.h - inicio.y)
  if (borde.includes('w')) {
    const derecha = inicio.x + inicio.w
    x = entre(inicio.x + dx, 0, derecha - minW)
    w = derecha - x
  }
  if (borde.includes('n')) {
    const abajo = inicio.y + inicio.h
    y = entre(inicio.y + dy, 0, abajo - minH)
    h = abajo - y
  }
  return { x: Math.round(x), y: Math.round(y), w: Math.round(w), h: Math.round(h) }
}
