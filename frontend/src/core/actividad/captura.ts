/**
 * Acciones de pantalla para la bitácora diaria (nivel máximo): al navegar
 * (con lo que duró la pantalla anterior), al imprimir y al tocar un elemento
 * con `data-actividad="<acción>"`. Salen en lote cada `intervaloSegundos` y al
 * ocultar la pestaña (`keepalive`). Solo si el backend dice que este usuario
 * se sigue (`GET /api/actividad/ajustes`); si no, no se anota nada.
 *
 * Lo arranca el shell al entrar y lo detiene al salir. `anotarActividad()`
 * sirve a cualquier pantalla para dejar una acción propia.
 */
import type { Router } from 'vue-router'
import { antesDeCerrarSesion } from '@/core/auth/session'
import { enviarActividad, fetchAjustesActividad } from './api'
import { crearCola, crearSeguimientoDePantalla, type EventoPantalla } from './cola'

const cola = crearCola()
const pantalla = crearSeguimientoDePantalla()
let activa = false
let temporizador: ReturnType<typeof setInterval> | null = null
let quitar: Array<() => void> = []
let enviando = false

export function anotarActividad(accion: string, detalle?: Record<string, unknown>) {
  if (!activa) return
  cola.anotar({ accion, pantalla: pantalla.ruta ?? location.pathname, fecha: Date.now(), ...(detalle ? { detalle } : {}) })
}

/** Manda lo pendiente (en lotes). Lo que falla vuelve a la cola. */
export async function vaciarActividad(keepalive = false) {
  if (!activa || enviando) return
  enviando = true
  try {
    while (cola.tamano > 0) {
      const lote = cola.tomar()
      try {
        await enviarActividad(lote, keepalive)
      } catch {
        cola.devolver(lote)
        break
      }
    }
  } finally {
    enviando = false
  }
}

export async function iniciarActividad(router: Router) {
  detenerActividad()
  let intervalo = 30
  try {
    const ajustes = await fetchAjustesActividad()
    if (!ajustes.registrar) return
    intervalo = ajustes.intervaloSegundos
  } catch {
    return
  }
  activa = true
  const alNavegar = (ruta: string, titulo: unknown) => {
    const e = pantalla.navegar(ruta, typeof titulo === 'string' ? titulo : undefined, Date.now())
    if (e) cola.anotar(e)
  }
  alNavegar(router.currentRoute.value.path, router.currentRoute.value.meta.title)
  quitar.push(router.afterEach((to, _from, fallo) => !fallo && alNavegar(to.path, to.meta.title)))

  const alImprimir = () => anotarActividad('imprimir', { titulo: document.title })
  window.addEventListener('beforeprint', alImprimir)
  quitar.push(() => window.removeEventListener('beforeprint', alImprimir))

  const alTocar = (ev: MouseEvent) => {
    const el = (ev.target as Element | null)?.closest?.('[data-actividad]')
    const accion = el?.getAttribute('data-actividad')
    if (accion) anotarActividad(accion, { texto: el?.textContent?.trim().slice(0, 80) || undefined } as EventoPantalla['detalle'])
  }
  document.addEventListener('click', alTocar, true)
  quitar.push(() => document.removeEventListener('click', alTocar, true))

  const alOcultar = () => document.visibilityState === 'hidden' && void vaciarActividad(true)
  document.addEventListener('visibilitychange', alOcultar)
  quitar.push(() => document.removeEventListener('visibilitychange', alOcultar))

  quitar.push(antesDeCerrarSesion(() => vaciarActividad()))

  temporizador = setInterval(() => void vaciarActividad(), Math.max(10, intervalo) * 1000)
}

/** Deja de escuchar. Lo pendiente sale antes del logout (`antesDeCerrarSesion`). */
export function detenerActividad() {
  if (temporizador) clearInterval(temporizador)
  temporizador = null
  quitar.forEach((q) => q())
  quitar = []
  activa = false
  pantalla.reiniciar()
}
