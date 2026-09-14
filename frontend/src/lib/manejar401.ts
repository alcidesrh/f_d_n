import { router } from "@/router";
import { useUserSessionStore } from "@/stores/session";

let redirigiendo = false;

/**
 * Manejo central de respuestas HTTP 401: limpia la sesión y redirige a /login.
 *
 * Idempotente: aunque varias peticiones fallen en paralelo con 401 (p. ej. el
 * arranque de /migracion dispara 4 llamadas a la vez), solo navega una vez.
 * Nunca redirige si ya estamos en la pantalla de login, para evitar loops.
 *
 * NO debe llamarse para el propio POST /login (credenciales inválidas): ahí el
 * 401 es la respuesta esperada y la UI muestra el error en el formulario.
 */
export function manejarNoAutorizado(): void {
  useUserSessionStore().clear();

  if (redirigiendo) return;
  if (router.currentRoute.value.name === "login") return;

  redirigiendo = true;
  router
    .push({ name: "login" })
    .catch(() => undefined)
    .then(() => {
      redirigiendo = false;
    });
}
