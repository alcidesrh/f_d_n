/*
 * Notificaciones del chat (Web Push), dentro del service worker que genera
 * vite-plugin-pwa (`workbox.importScripts`). La carga es lo que arma el
 * backend (`App\Chat\Push\AvisosPush::contenido`): { titulo, cuerpo, url, etiqueta }.
 */

/** La app está a la vista: el chat ya avisa dentro, la notificación sobra. */
async function appALaVista() {
  const ventanas = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
  return ventanas.some((c) => c.visibilityState === "visible" && c.focused);
}

self.addEventListener("push", (evento) => {
  let datos = {};
  try {
    datos = evento.data ? evento.data.json() : {};
  } catch {
    return;
  }
  evento.waitUntil(
    appALaVista().then((visible) => {
      if (visible) return;
      return self.registration.showNotification(datos.titulo || "Mensaje nuevo", {
        body: datos.cuerpo || "",
        tag: datos.etiqueta,
        renotify: Boolean(datos.etiqueta),
        icon: "/pwa/icono-192.png",
        badge: "/pwa/icono-192.png",
        lang: "es",
        data: { url: datos.url || "/chat" },
      });
    }),
  );
});

/** Abre la conversación: en la ventana de la app si hay una (sin recargarla), si no, en una nueva. */
self.addEventListener("notificationclick", (evento) => {
  evento.notification.close();
  const url = new URL(evento.notification.data?.url || "/chat", self.location.origin);
  if (url.origin !== self.location.origin) return;
  evento.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((ventanas) => {
      const ventana = ventanas.find((c) => new URL(c.url).origin === url.origin);
      if (!ventana) return self.clients.openWindow(url.href);
      ventana.postMessage({ tipo: "abrir", url: url.pathname + url.search });
      return ventana.focus();
    }),
  );
});
