# AGENTS.md — App móvil del personal

Shell nativo con Capacitor (ADR-060). **No tiene pantallas propias**: su WebView carga el frontend
desplegado (`frontend/`) y abre en `/chat`. Lo que se ve en el teléfono se cambia en `frontend/`; aquí
solo vive lo nativo (permisos, ícono, plugins).

## Comandos (en `movil/`)

| Comando | Descripción |
|---|---|
| `npm ci` | Dependencias (npm, con `package-lock.json`) |
| `npm run sync` | Copia `www/` y la configuración al proyecto Android. `FDN_APP_URL=https://… npm run sync` apunta a otro servidor (por defecto, producción) |
| `npm run apk` | `sync` + `./gradlew assembleDebug` → `android/app/build/outputs/apk/debug/app-debug.apk` (requiere JDK 21 y el SDK de Android) |
| `npm run abrir` | Abre el proyecto en Android Studio |
| `python3 recursos/iconos.py` | Regenera ícono y pantalla de arranque desde `frontend/public/pwa/` |

Sin SDK de Android a mano: la acción «App móvil (Android)» de GitHub compila el APK de pruebas en cada
cambio de `movil/` (artefacto `fdn-movil-debug`) y a mano con otra URL.

## Qué hay

| Ruta | Qué es |
|---|---|
| `capacitor.config.ts` | Id de la app, servidor (`FDN_APP_URL`), arranque en `/chat`, pantalla de error |
| `www/index.html` | Lo único empaquetado: «Sin conexión» con reintento. Sin dependencias ni plugins |
| `android/` | Proyecto nativo generado por Capacitor, en git. `app/src/main/assets/` es generado por `sync` (fuera de git) |
| `recursos/iconos.py` | Ícono adaptable, redondo y arranque desde los íconos de la PWA |

## Notificaciones (FCM, ADR-061)

Hacen falta dos archivos de un proyecto de Firebase; sin ellos todo compila y funciona, sin notificaciones:

1. En la [consola de Firebase](https://console.firebase.google.com): crear el proyecto y sumarle una app
   Android con el paquete `gt.fuentesdelnorte.personal`.
2. **`google-services.json`** (de esa app Android) → `movil/android/app/google-services.json`. Son
   identificadores, no secretos: puede ir en git, o como secreto `GOOGLE_SERVICES_JSON` de GitHub Actions.
3. **Cuenta de servicio** (Configuración del proyecto → Cuentas de servicio → Generar nueva clave privada):
   ese JSON sí es secreto. Va en el servidor, fuera de git, y `PUSH_FCM_CREDENCIAL` (en `.env.local` o
   `.env.prod.local`) lleva su ruta dentro del contenedor del backend. Lo leen `backend` y `worker`.
4. Recompilar el APK. En la app: Mi cuenta → Notificaciones.

El ícono de la barra de estado es `res/drawable/ic_notificacion.xml`; el canal `chat` lo crea la app al
activarlas (`frontend/src/core/chat/pushNativo.ts`).

## Reglas

- Un APK apunta a un solo servidor, siempre por HTTPS. Para probar contra `make dev` en la red local, el
  teléfono tiene que confiar en el certificado de Caddy (o usar `make vps`, que tiene uno público).
- Un plugin nativo se suma aquí (`npm install @capacitor/…` + `npm run sync`) y se usa desde `frontend/`
  preguntando antes `window.Capacitor?.isNativePlatform()`: el mismo código corre en el navegador.
- No hay Web Push ni `window.print()` dentro del WebView (ADR-060, consecuencias). Las notificaciones del
  chat llegan por FCM (ADR-061).
- La firma de publicación (keystore) no va en git.
