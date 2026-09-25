/**
 * Despliegue de un enlace del sidebar en modo `mini` al pasar el mouse: el
 * enlace se ensancha hasta el ancho del modo abierto (flotando sobre el
 * contenido) y el texto entra deslizándose junto al ícono hasta su posición
 * y tamaño de siempre. Se mantiene mientras dure el hover.
 *
 * Un timeline por enlace: `play()` al entrar y `reverse()` al salir, así una
 * entrada/salida rápida invierte la animación desde donde esté en lugar de
 * saltar. Al terminar de revertir se limpian los estilos en línea.
 */
import { gsap } from 'gsap'

export interface FlyoutOptions {
  side: 'left' | 'right'
  /** Ancho del sidebar en `mini` y en `open` (px). */
  mini: number
  open: number
}

/** Distancia (px) desde la que entra el texto. */
const SLIDE = 24
const DURATION = 0.35

const flyouts = new Map<HTMLElement, gsap.core.Timeline>()

function cssVar(name: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

function build(link: HTMLElement, { side, mini, open }: FlyoutOptions): gsap.core.Timeline {
  const text = link.querySelector<HTMLElement>('.menu-text')
  const left = side === 'left'
  const shadow = cssVar('--p-surface-300')

  const timeline = gsap.timeline({
    paused: true,
    defaults: { duration: DURATION, ease: 'power3.out' },
    onReverseComplete: () => reset(link),
  })

  timeline.set(link, {
    // Dentro de una fila flex (`NavTree`) el enlace se encogería al ancho de la barra.
    flexShrink: 0,
    zIndex: 999,
    backgroundColor: cssVar('--bg') || cssVar('--p-content-background'),
    borderRadius: left ? '0 8px 8px 0' : '8px 0 0 8px',
    // En el sidebar derecho crece hacia la izquierda con el ícono fijo en su sitio.
    ...(left ? {} : { flexDirection: 'row-reverse' }),
  })
  if (text && !left) timeline.set(text, { marginLeft: 0, marginRight: 15 })

  timeline.fromTo(
    link,
    { width: mini, x: 0 },
    {
      width: open,
      x: left ? 0 : mini - open,
      boxShadow: `${left ? 1 : -1}px 0 6px ${shadow}`,
    },
    0,
  )
  if (text)
    timeline.fromTo(
      text,
      { opacity: 0, x: left ? -SLIDE : SLIDE },
      { opacity: 1, x: 0, ease: 'power2.out' },
      0.06,
    )

  return timeline
}

function reset(link: HTMLElement) {
  flyouts.get(link)?.kill()
  flyouts.delete(link)
  gsap.set(link, {
    clearProps:
      'flexShrink,zIndex,backgroundColor,borderRadius,flexDirection,width,x,boxShadow,transform',
  })
  const text = link.querySelector<HTMLElement>('.menu-text')
  // El texto sigue oculto en `mini` (lo dejó así `sidebarUpdate`).
  if (text) gsap.set(text, { clearProps: 'marginLeft,marginRight,x,transform' })
}

export function openFlyout(link: HTMLElement, options: FlyoutOptions): void {
  let timeline = flyouts.get(link)
  if (!timeline) {
    timeline = build(link, options)
    flyouts.set(link, timeline)
  }
  timeline.play()
}

export function closeFlyout(link: HTMLElement): void {
  flyouts.get(link)?.reverse()
}

/** Cierra de golpe los despliegues de un lado (al cambiar de modo). */
export function resetFlyouts(side: 'left' | 'right'): void {
  for (const link of [...flyouts.keys()])
    if (link.closest(`.sidebar.${side}`) || !link.isConnected) reset(link)
}
