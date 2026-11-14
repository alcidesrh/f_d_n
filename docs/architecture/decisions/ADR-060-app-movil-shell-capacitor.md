# ADR-060: App móvil del personal: shell nativo con Capacitor sobre la app web

**Estado:** Aceptada (prototipo; primero Android).

## Contexto

El frontend ya es mobile-first (ADR-020, ADR-053) e instalable como PWA que abre en el chat. Como PWA no
alcanza para lo que el negocio va a pedirle al teléfono: push confiable con la app cerrada (sobre todo en
iOS), y más adelante impresoras térmicas Bluetooth, lector de códigos para el abordaje y las encomiendas y
posición en segundo plano. Reescribir el chat, los menús por capacidades y los mapas en Flutter o React
Native duplicaría pantallas que ya existen y que cambian cada semana.

## Decisión

- **Capacitor** en `movil/`, aparte de `frontend/`: un proyecto nativo (Android en `movil/android/`) cuyo
  WebView muestra la app del personal. Las piezas nativas llegan como plugins, una por necesidad.
- **El WebView carga la app desplegada** (`server.url`, por defecto el dominio de producción; `FDN_APP_URL`
  lo cambia al sincronizar), no una copia empaquetada en el APK. Abre en `/chat` (`server.appStartPath`),
  igual que la PWA. La sesión (Bearer en el almacenamiento del WebView), los menús por capacidades y la
  navegación son los de la web, sin cambios.
- Dentro del APK solo va `movil/www/index.html`: la pantalla de «Sin conexión» (`server.errorPath`), que
  reintenta sola al volver la red.
- `@capacitor/app` da el «atrás» de Android: retrocede en el historial de la app y, en la primera
  pantalla, sale.
- Sin copia de seguridad de Android (`allowBackup="false"`): el teléfono puede ser de varios usuarios y
  el token de sesión no debe viajar a otra instalación.
- El APK de pruebas lo compila GitHub Actions (`.github/workflows/movil-android.yml`) y queda como
  artefacto de la corrida.

## Por qué cargar la app desplegada y no empaquetarla

| | Desplegada (elegida) | Empaquetada en el APK |
|---|---|---|
| Versión del frontend | Siempre la del backend; `make front` llega a todos los teléfonos | La del APK instalado: ventas y reglas viejas hasta reinstalar |
| Origen | El del backend: mismas rutas relativas, Mercure, service worker y sesión | `https://localhost`: hace falta CORS, URLs absolutas y revisar cada descarga y foto |
| Cambios al frontend | Ninguno | `core/config.ts`, Caddy y todo lo que asume mismo origen |
| Sin red | No abre (pantalla de «Sin conexión») | Abre la shell, pero toda operación necesita la API |
| Tiendas | Google Play lo admite; Apple puede objetar una app que solo envuelve un sitio | Sin objeción |

La app del personal no hace nada sin la API, así que abrir sin red no aporta, y un frontend más viejo que
el backend es justo lo que ADR-036 evita en el navegador. Si se publica en la App Store o se quiere venta
sin conexión, se revisa: pasar a empaquetada es cambiar `webDir` y quitar `server.url`, más el trabajo de
origen de la tabla.

## Consecuencias

- Web Push no existe en el WebView de Android: en la app las notificaciones llegan por FCM (ADR-061).
- `window.print()` no funciona en el WebView: tickets, recibos y guías no se imprimen desde la app hasta
  que haya un plugin de impresión.
- Los plugins nativos solo existen dentro de la app: el código web que los use pregunta antes
  (`window.Capacitor?.isNativePlatform()`).
- Un APK queda atado a un servidor: uno por entorno (producción, desarrollo en la red local).
