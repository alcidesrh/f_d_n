/**
 * Volver a donde iba el usuario tras iniciar sesión: el guard del router y el
 * 401 central lo mandan al login con `?redirect=<ruta>`, y el login lo usa.
 * Solo se aceptan rutas internas (nunca otra origen ni el propio login).
 */
import type { RouteLocationRaw } from 'vue-router'

export const REDIRECT_QUERY = 'redirect'

/** La ruta interna a la que volver, o null si no es segura o no aporta. */
export function destinoSeguro(valor: unknown): string | null {
  const ruta = Array.isArray(valor) ? valor[0] : valor
  if (
    typeof ruta !== 'string' ||
    !ruta.startsWith('/') ||
    ruta.startsWith('//') ||
    ruta.includes('\\')
  )
    return null
  const camino = ruta.split(/[?#]/, 1)[0]
  if (camino === '/' || camino === '/login') return null
  return ruta
}

/** Ubicación del login que recuerda `fullPath` (si vale la pena recordarlo). */
export function loginHacia(fullPath: string): RouteLocationRaw {
  const destino = destinoSeguro(fullPath)
  return destino ? { name: 'login', query: { [REDIRECT_QUERY]: destino } } : { name: 'login' }
}
