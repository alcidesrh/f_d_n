/**
 * Marcador del bus en los mapas de seguimiento (en vivo y reproducción): un botón
 * redondo del color de la empresa con una flecha según el rumbo. Se crea una vez
 * (`elementoBus`, el elemento de un `Marker` de MapLibre) y se repinta en cada
 * cuadro (`pintarBus`) sin rehacer el DOM.
 */
import './marcador.css'

export interface AspectoBus {
  color: string
  rumbo: number
  activo?: boolean
  quieto?: boolean
}

export function elementoBus(titulo: string): HTMLButtonElement {
  const boton = document.createElement('button')
  boton.type = 'button'
  boton.className = 'mapa-boton bus-marker'
  boton.title = titulo
  boton.setAttribute('aria-label', titulo)
  boton.innerHTML = '<span class="bus-marker-body"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 2 19 21 12 17 5 21Z" fill="#fff"/></svg></span>'
  // El ratón no le da el foco: el navegador desplazaría la página para mostrarlo y el clic se perdería.
  boton.addEventListener('mousedown', (e) => e.preventDefault())
  return boton
}

export function pintarBus(boton: HTMLElement, { color, rumbo, activo = false, quieto = false }: AspectoBus): void {
  const cuerpo = boton.firstElementChild as HTMLElement | null
  const flecha = cuerpo?.firstElementChild as SVGElement | null
  if (!cuerpo || !flecha) return
  cuerpo.style.setProperty('--c', color)
  cuerpo.classList.toggle('is-active', activo)
  cuerpo.classList.toggle('is-still', quieto)
  flecha.style.transform = `rotate(${rumbo.toFixed(0)}deg)`
  // El elegido, encima de los demás.
  boton.style.zIndex = activo ? '2' : '1'
}
