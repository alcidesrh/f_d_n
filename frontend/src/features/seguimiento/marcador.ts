/**
 * Marcador del bus en los mapas de seguimiento (en vivo y reproducción): un botón
 * redondo del color de la empresa con un bus al centro y, en el borde, una punta
 * que señala el rumbo. Se crea una vez
 * (`elementoBus`, el elemento de un `Marker` de MapLibre) y se repinta en cada
 * cuadro (`pintarBus`) sin rehacer el DOM.
 */
import "./marcador.css";

export interface AspectoBus {
  color: string;
  rumbo: number;
  activo?: boolean;
  quieto?: boolean;
}

export function elementoBus(titulo: string): HTMLButtonElement {
  const boton = document.createElement("button");
  boton.type = "button";
  boton.className = "mapa-boton bus-marker";
  boton.title = titulo;
  boton.setAttribute("aria-label", titulo);
  // El bus (Material Icons, «directions_bus») queda derecho; lo que gira es la punta del rumbo.
  boton.innerHTML = '<span class="bus-marker-body"><span class="bus-marker-rumbo" aria-hidden="true"></span>' + '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="#fff" d="M4 16c0 .88.39 1.67 1 2.22V20c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h8v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1.78c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17m9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5m1.5-6H6V6h12z"/></svg></span>';
  // El ratón no le da el foco: el navegador desplazaría la página para mostrarlo y el clic se perdería.
  boton.addEventListener("mousedown", (e) => e.preventDefault());
  return boton;
}

export function pintarBus(boton: HTMLElement, { color, rumbo, activo = false, quieto = false }: AspectoBus): void {
  const cuerpo = boton.firstElementChild as HTMLElement | null;
  const flecha = cuerpo?.querySelector<HTMLElement>(".bus-marker-rumbo");
  if (!cuerpo || !flecha) return;
  cuerpo.style.setProperty("--c", color);
  cuerpo.classList.toggle("is-active", activo);
  cuerpo.classList.toggle("is-still", quieto);
  flecha.style.transform = `rotate(${rumbo.toFixed(0)}deg)`;
  // El elegido, encima de los demás.
  boton.style.zIndex = activo ? "2" : "1";
}
