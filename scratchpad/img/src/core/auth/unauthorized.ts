import { router } from '@/app/router'
import { loginHacia } from './redirect'
import { useSessionStore } from './session'

let redirecting = false

/**
 * La página en la que está el usuario. Con la sesión vencida al abrir un
 * enlace, el router aún no terminó esa primera navegación (`currentRoute`
 * sigue en `/`): la dirección del navegador es la fuente fiable.
 */
function paginaActual(): string {
  const actual = router.currentRoute.value
  return actual.matched.length ? actual.fullPath : router.options.history.location
}

/**
 * Respuesta central a un 401 (token desconocido o expirado): limpia la sesión
 * y lleva al login recordando la página en la que estaba. Idempotente: si varias peticiones fallan a la vez solo
 * navega una vez, y nunca desde la propia pantalla de login.
 */
export function handleUnauthorized(): void {
  useSessionStore().clear()
  if (redirecting || router.currentRoute.value.name === 'login') return
  redirecting = true
  void router
    .push(loginHacia(paginaActual()))
    .catch(() => undefined)
    .finally(() => (redirecting = false))
}
