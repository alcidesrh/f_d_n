import { router } from "@/app/router";
import { useSessionStore } from "./session";

let redirecting = false;

/**
 * Respuesta central a un 401 (token desconocido o expirado): limpia la sesión
 * y lleva al login. Idempotente: si varias peticiones fallan a la vez solo
 * navega una vez, y nunca desde la propia pantalla de login.
 */
export function handleUnauthorized(): void {
  useSessionStore().clear();
  if (redirecting || router.currentRoute.value.name === "login") return;
  redirecting = true;
  void router
    .push({ name: "login" })
    .catch(() => undefined)
    .finally(() => (redirecting = false));
}
