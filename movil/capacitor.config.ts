import type { CapacitorConfig } from '@capacitor/cli'

/**
 * Shell nativo del personal (ADR-060): un WebView que carga la app web desplegada,
 * no una copia empaquetada. Así el teléfono corre siempre la misma versión que el
 * backend y un despliegue no obliga a reinstalar la app.
 *
 * `FDN_APP_URL` elige el servidor al sincronizar (`npm run sync`): el dominio de
 * producción por defecto; en desarrollo, p. ej. `https://192.168.1.20` (el Caddy
 * de `make dev` en la red local).
 */
const url = (process.env.FDN_APP_URL ?? 'https://fdn2.duckdns.org').replace(/\/$/, '')

const config: CapacitorConfig = {
  appId: 'gt.fuentesdelnorte.personal',
  appName: 'Fuentes del Norte',
  // Solo lo que va dentro del APK: la pantalla de «sin conexión».
  webDir: 'www',
  server: {
    url,
    // Como la PWA: la app abre en el chat.
    appStartPath: '/chat',
    // El servidor no respondió: pantalla local con «Reintentar».
    errorPath: 'index.html',
    allowNavigation: [new URL(url).host],
  },
  android: {
    // Solo HTTPS, también en desarrollo (Caddy ya lo sirve así).
    allowMixedContent: false,
  },
}

export default config
