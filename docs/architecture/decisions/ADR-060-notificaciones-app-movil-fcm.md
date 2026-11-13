# ADR-060: Notificaciones del chat en la app móvil (Firebase Cloud Messaging)

**Estado:** Aceptada.

## Contexto

El chat avisa con la app cerrada por Web Push (`App\Chat\Push`, ADR-026). Dentro del shell de la app
móvil (ADR-059) no existe: el WebView de Android no tiene `PushManager`. En Android, que llegue una
notificación con la app cerrada pasa por Firebase Cloud Messaging; lo demás (una conexión propia
siempre abierta) gasta batería y el sistema la corta.

## Decisión

- **Un teléfono es un `DispositivoPush`**: usuario, token de FCM (único) y plataforma. Como la suscripción
  del navegador, es de quien lo registró por última vez: al entrar otro usuario en el mismo teléfono pasa
  a ser suyo, y al cerrar sesión se quita. `GET/POST/DELETE /api/chat/push/dispositivo`.
- **El mismo mensaje, dos canales.** `AvisosPush` sigue armando `{ titulo, cuerpo, url, etiqueta }` y
  despachando `EnviarAvisoPush`; el handler lo manda por Web Push a los navegadores y por `Push\Fcm` a los
  teléfonos. Quien tiene el navegador y la app recibe en los dos.
- **`Push\Fcm` habla la API HTTP v1 sin dependencias nuevas**: firma un JWT con la clave de la cuenta de
  servicio (`openssl`), lo cambia por un token de acceso de Google (se guarda en caché mientras vale) y
  hace el `POST`. `PUSH_FCM_CREDENCIAL` es la ruta de ese JSON; vacía, la app no ofrece notificaciones.
  Un teléfono que Firebase ya no conoce (404 `UNREGISTERED`, token inválido) se borra.
- **Mensaje con `notification`**: Android lo muestra solo con la app cerrada o en segundo plano (canal
  `chat`, la etiqueta de la conversación como `tag`: el nuevo reemplaza al anterior). Con la app a la
  vista no se muestra: el chat ya avisa dentro, igual que en la web. `data.url` es lo que abre al tocarla.
- **En el frontend, un solo interruptor.** `core/chat/push.ts` elige: en el navegador, Web Push; dentro de
  la app (`esAppNativa()`), `pushNativo.ts` con el plugin `@capacitor/push-notifications`, que se baja solo
  ahí. Pide el permiso de Android al activarlas, crea el canal y registra el token; al arrancar revisa si
  Firebase lo cambió. Tocar la notificación navega con el router, solo a rutas de la app.

## Consecuencias

- Hace falta un proyecto de Firebase: `google-services.json` en el APK y la cuenta de servicio en el
  servidor (`movil/AGENTS.md`). Sin ellos todo sigue funcionando, sin notificaciones en la app.
- El título y el fragmento del mensaje pasan por Firebase sin cifrar de extremo a extremo (en Web Push sí
  van cifrados para el navegador). Es el mismo texto de 100 caracteres; si un día importa, se cambia a un
  mensaje solo de datos y la app pide el contenido al backend.
- Migración `Version20261113120000` (tabla `dispositivo_push`).
- iOS queda para cuando haya app de iOS: el backend ya acepta la plataforma y FCM reenvía a APNs.
