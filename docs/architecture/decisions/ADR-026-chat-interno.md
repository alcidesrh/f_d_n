# ADR-026: Chat interno integrado al modelo de datos (primera versión)

**Estado:** Aceptada

## Contexto

La coordinación entre la administración, las más de 30 estaciones y las agencias se hace hoy por WhatsApp: un grupo único para administración y estaciones (reporte de cada salida con foto y conteo de asientos, indicaciones, imprevistos) y otro de las agencias con la administración (acreditaciones, avisos). WhatsApp no conoce el sistema: para que alguien revise un boleto se manda el id por texto, se copia, se busca en el listado y se abre el detalle. Los reportes de salida se cuentan a mano aunque la venta ya está en la base de datos.

Se busca reemplazarlo por un chat propio que, además de conversar, comparta registros del sistema como vistas vivas.

## Decisión

### Modelo

Tres entidades, solo usadas por `App\Chat` y la API REST (no se publican en GraphQL ni en el CRUD genérico):

- `ChatCanal`: conversación `directo` (dos personas, única por par con `clave = "{menor}-{mayor}"`) o `grupo` (con nombre). `actividad` ordena la bandeja.
- `ChatMiembro`: usuario en un canal y `ultimoLeido` (id del último mensaje leído; nunca retrocede). Los no leídos se cuentan con una consulta agrupada, sin contadores que mantener.
- `ChatMensaje`: texto, `adjuntos` (JSONB) = lista de **referencias** `{tipo, id}` a registros del sistema (`BoletoAsiento`, `Salida`, `Bus`…), `archivos` y `respuestaA` (el mensaje del mismo canal al que responde). Sin autor = aviso del sistema.
- `ChatArchivo`: foto o documento subido (metadatos; el contenido en disco).
- Canal `sistema`: "Avisos del sistema", uno por usuario (`clave = "s-{id}"`), solo lectura.

### Lo compartido es una referencia, no una copia

Un adjunto no guarda datos: al leer, `App\Chat\Tarjeta\Tarjetas` lo resuelve en una **tarjeta** con el estado actual del registro y **con los permisos de quien lee** (no de quien envía), en una consulta por tipo para toda la página de mensajes. Cada tarjeta trae `estado` (`ok`, `sin_acceso`, `no_existe`) y `datos` (siempre con `titulo`). Compartir no abre permisos: una estación sin `boletoasiento.read` ve "No tiene permiso". El filtro multiempresa (ADR-015) aplica igual.

Tarjetas propias (servicios con la etiqueta `app.chat.tarjeta`): `TarjetaBoleto` (estado, pasajero, asiento, salida, venta) y `TarjetaSalida` (el mismo detalle que "Ver" en `/salidas`, con permiso `salida.ver`: ocupación por clase y canal, cobrado y croquis — el reporte de salida que se hacía a mano, calculado). Cualquier otra entidad de `App\Entity` usa `TarjetaGenerica` (su `label` y enlace al formulario, permiso `{entidad}.read`). Al enviar se valida que el tipo exista y que el autor pueda ver cada registro (máximo 20 por mensaje).

Contrapartida aceptada: una tarjeta muestra el estado de hoy, no el del momento del envío. Para conversar sobre un boleto o una salida es lo que se quiere (si después se anuló, se ve anulado). Si en el futuro hace falta un registro inmutable (p. ej. el reporte oficial de salida al despegar), se agregará una instantánea opcional junto a la referencia.

### Quién habla con quién (`Directorio`)

Regla pura, con tests: el ámbito de un usuario sale de `Usuario.agencia` / `Usuario.estacion` (sin ninguno = administración). Administración y estaciones conversan entre todas; una agencia solo con su propia gente y con la administración, nunca con estaciones ni con otras agencias. En un grupo la regla vale para cada par de miembros. Se aplica al listar contactos, abrir directos, crear grupos y en cada mensaje directo.

### Fotos y archivos

`App\Chat\Archivos`: se suben antes de enviar (`POST /api/chat/archivos`, multipart) y quedan sueltos hasta que un mensaje del mismo autor los usa; los sueltos de más de 24 h los borra `app:chat:purgar-archivos` (cron). El tipo se decide por el **contenido**, no por la extensión ni el navegador: imágenes que reconoce `getimagesize` (JPEG, PNG, GIF, WebP), PDF (`%PDF-`), Word/Excel (zip + extensión) y texto/CSV (UTF-8 sin nulos); máximo 10 MB (`upload_max_filesize` en `20-app.*.ini`). Se guardan en `CHAT_ARCHIVOS_DIR` (por defecto `/data/chat`, el volumen persistente del contenedor) con nombre aleatorio.

Se sirven por una **URL firmada que vence** (HMAC con `kernel.secret`, 24–30 h, estable por ventanas de 6 h para que el navegador la cachee): `GET /api/chat/archivos/{id}/{firma}?exp=…`, ruta pública en `security.yaml` porque una etiqueta `<img>` no manda el Bearer. Solo la recibe quien puede leer el mensaje. Respuesta con `nosniff` y CSP `sandbox`; lo que no es imagen ni PDF se descarga, nunca se muestra en línea.

El navegador reduce las fotos antes de subirlas (lado mayor 1600 px, JPEG 0.82: una foto de 4 MB queda en ~250 KB), pensado en estaciones con internet modesto.

### Avisos del sistema por eventos

Otros módulos no conocen el chat: emiten eventos de dominio y `App\Chat\AvisosDelSistema` los traduce en mensajes del canal de sistema de cada destinatario. Los eventos se despachan con `Transaccion::despuesDeConfirmar()`, que difiere el efecto hasta el commit de la transacción más externa y lo descarta si se revierte: nunca se avisa algo que no quedó en la base de datos. Hoy:

- `Venta\Agencia\SaldoAcreditado` (depósito o ajuste): a los usuarios de la agencia, con el importe, la bonificación y el saldo disponible (lo que hoy se pide y confirma por WhatsApp).
- `Salida\SalidasAnuladas`: a los usuarios de la estación de origen, con las salidas como tarjetas.

Sumar un aviso = emitir un evento tras confirmar y escucharlo en `AvisosDelSistema`. Un fallo al avisar se registra y no rompe la operación original.

### Respuestas y "visto"

Un mensaje puede citar otro del mismo canal (`respuestaA`); la cita se muestra con autor y extracto y lleva al original. La bandeja trae, por canal, hasta dónde leyó cada uno de los demás (`lecturas`); el aviso `leido` se publica a todos los miembros, así el ✓ (enviado) pasa a ✓✓ (visto por todos) en vivo. En grupos se ve quiénes lo vieron.

### Tiempo real

Mercure (ADR-005) con un **tópico privado por usuario** (`/chat/usuarios/{id}`, `Update(private: true)`). `GET /api/chat/token` entrega un JWT de suscriptor solo para ese tópico (12 h); el navegador lo pasa en el parámetro `authorization` (Caddy ya lo oculta del log). Los avisos (`mensaje`, `leido`, `canal`) no llevan tarjetas: el cliente pide los mensajes nuevos y los ve con sus permisos. Sin Mercure el cliente refresca la bandeja cada 30 s.

### API

`/api/chat/*`, cualquier usuario con sesión: `token`, `contactos`, `recursos` (tipos que el usuario puede adjuntar), `canales` (bandeja), `canales/directo`, `canales/grupo`, `canales/{id}/mensajes` (GET con `antes`/`despues`, POST), `canales/{id}/leido`, `compartir` (los mismos registros a varias personas y/o grupos).

### Frontend

`core/chat/` (tipos, API, reglas puras con tests, store con la suscripción), `features/chat/` (`/chat/:canal?`: bandeja con búsqueda única de conversaciones y personas, conversación por días y rachas, redactor con adjuntos por tipo e id, tarjetas por tipo en `tarjetas/catalogo.ts`, aviso flotante y notificación del navegador). `shared/chat/EnviarPorChatDialog` es el "Enviar por chat" que usan el listado genérico (modo selección) y `/salidas` ("Reportar por chat"). La cabecera muestra el total de no leídos.

### El chat como capa de la app (v3)

El chat no es una página más: es una capa sobre el trabajo, para hablar de lo que se tiene delante.

- **Tres modos** (`core/chat/ventana.ts`, geometría pura con tests; estado persistido en `features/chat/ventana.ts`):
  - `centro`: la página `/chat`, como hasta ahora.
  - `esquina`: ventana abajo a la derecha; minimizada asoma solo la barra.
  - `flotante`: se mueve por la barra y se redimensiona por bordes y esquinas (GSAP Draggable); minimizada se encoge a la barra en su sitio.

  Todas maximizan a pantalla completa; esquina y flotante además minimizan y cierran. En el móvil, abierta ocupa la pantalla. El ícono del encabezado la vuelve a mostrar tal como estaba (modo, minimizada o maximizada).
- **Ventana del shell**: en esquina y flotante la monta `AppShell` (`ChatVentana`), así que sigue abierta al navegar. El router convierte `/chat/:canal?adjuntar=…` en "abrir la ventana" sin salir de la página.
  - Cerrada o minimizada no se desmonta (no se pierde lo escrito), pero tampoco marca leído lo que llega (`Conversacion.activa`).
  - El aviso flotante cede el lugar a la propia ventana, que destella.
- **Selector de registros** en lugar de escribir ids. Un modal con tres paneles:
  - los recursos que el backend deja adjuntar (`GET /api/chat/recursos`: misma regla que las tarjetas, sin secretos como tokens o credenciales);
  - el listado genérico de la entidad en modo selección (filtros, orden, páginas);
  - lo elegido, agrupado por recurso como un carrito.

  La selección sobrevive a cambiar de página o de recurso; hasta 20 por mensaje.
- **Lo recibido, plegado**: los registros de un mensaje llegan agrupados por tipo y plegados (aunque sea uno), con la cuenta y un avance de los títulos; al desplegar, las tarjetas vivas (`tarjetas/TarjetasMensaje.vue`).
- **Centro sin remontar**: la ruta del chat lleva `meta.conservar`, así `App.vue` no la vuelve a montar al cambiar de conversación (la transición de rutas usa `fullPath` como clave). Su raíz es un elemento, no el `Teleport` de la pantalla completa: la transición `out-in` lo necesita para salir.
- **Lo que hay en pantalla**: cada página anuncia lo que muestra (`anunciarEnPantalla` en `shared/chat/integracion.ts`): el registro del formulario genérico, la selección de un listado, la salida abierta en `/salidas`. El redactor lo ofrece con un clic.

Para no romper ADR-017 (una feature no importa a otra), el listado genérico llega al chat por inyección: `AppShell` provee `LISTADO_DE_REGISTROS` con `ListPage`, que con `v-model:seleccion` funciona como selector.

## Consecuencias

**Positivas:**

- Un boleto o una salida se comparte desde donde se ve y se abre con un clic; quien recibe ve el dato al día.
- El reporte de salida deja de contarse a mano: la tarjeta lo calcula desde la venta.
- La separación agencias/estaciones de WhatsApp queda como regla del sistema, no como costumbre.
- Sumar un tipo de tarjeta es una clase en el backend y un componente en `tarjetas/catalogo.ts`; sin tarjeta propia ya funciona la genérica.
- Se conversa sin dejar la pantalla de trabajo, y adjuntar es elegir, no recordar ids.

**Negativas / pendiente:**

- Archivos en el disco del contenedor (volumen `caddy_data`): entran en el respaldo solo si se respalda ese volumen. Para varios servidores habrá que pasar a un almacenamiento compartido (S3 o similar) detrás de `Archivos`.
- Una URL firmada copiada sirve hasta que vence (máximo 30 h).
- Sin edición ni borrado de mensajes.
- Avisos del sistema solo para saldo de agencias y salidas anuladas; "salida iniciada" e imprevistos esperan a que existan en el modelo nuevo (hoy el estado "iniciada" solo viene del legado).
- El selector solo ofrece entidades con colección en GraphQL: `BoletoAsiento` no la tiene, así que los boletos sueltos se comparten desde su venta o desde `?adjuntar=`, hasta que se exponga.
- La ventana queda por debajo de los overlays y diálogos de PrimeVue (z-index 990 < 1000), que el propio chat abre; un diálogo modal de otra pantalla la tapa mientras está abierto.
- La tarjeta de salida calcula el detalle completo por salida; con muchas salidas en una misma página de mensajes convendrá una consulta por lotes.
