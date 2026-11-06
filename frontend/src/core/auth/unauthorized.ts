import { router } from '@/app/router'
import { loginHacia } from './redirect'
import { useSessionStore } from './session'

let redirecting = false

/** Al recargar, la app avisa que la suplantación terminó (venció o la cerraron). */
export const AVISO_SUPLANTACION_VENCIDA = 'suplantacion:vencida'

/**
 * La página en la que está el usuario. Con la sesión vencida al abrir un
 * enlace, el router aún no terminó esa primera navegación (`currentRoute`
 * sigue en `/`): la dirección del navegador es la fuente fiable.
 */
function paginaActual(): string {
  const actual = router.currentRoute.value
  return actual.matched.length ? actual.fullPath : router.options.history.location
}

/** Recarga la app (al volver de una suplantación vencida). */
let recargar = (destino: string) => window.location.assign(destino)

/** @internal pruebas */
export function cambiarRecargaAlVencer(f: (destino: string) => void): void {
  recargar = f
}

/**
 * Respuesta central a un 401 (token desconocido o expirado): limpia la sesión
 * y lleva al login recordando la página en la que estaba. Idempotente: si varias peticiones fallan a la vez solo
 * navega una vez, y nunca desde la propia pantalla de login.
 *
 * Si vence el token de una suplantación, en vez del login vuelve a la cuenta
 * propia del administrador.
 */
export function handleUnauthorized(): void {
  const session = useSessionStore()
  if (session.recuperarOriginal()) {
    if (redirecting) return
    redirecting = true
    sessionStorage.setItem(AVISO_SUPLANTACION_VENCIDA, '1')
    recargar('/')
    return
  }
  session.clear()
  if (redirecting || router.currentRoute.value.name === 'login') return
  redirecting = true
  void router
    .push(loginHacia(paginaActual()))
    .catch(() => undefined)
    .finally(() => (redirecting = false))
}
