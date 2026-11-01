# ADR-048: Correo de la empresa

**Estado:** Aceptada

## Contexto

El sistema y la página del legado ya mandan correos por Mailpace (el sistema firma como `@sistemafdn.com` y la página como `@transportesfuentedelnorte.com`), pero nadie tiene un buzón: lo que un cliente o un proveedor contesta no llega a ninguna parte del sistema. Se pidió que cada usuario tenga su dirección `usuario@sistemafdn.com` y un correo dentro del sistema: bandeja con leído y no leído, redactar, borradores como los de Gmail (empiezan solos al escribir y no se pierden hasta descartarlos) y algunos filtros. No debía ser un cliente de correo convencional, sino una herramienta de trabajo junto al chat (ADR-026).

Mailpace manda y también recibe:

- **Salida:** SMTP o API con un token por dominio verificado. Symfony Mailer ya está instalado.
- **Entrada:** un webhook JSON firmado con Ed25519 (cabecera `X-MailPace-Signature`, clave pública en el panel del dominio). Solo acepta un subdominio dedicado (MX o CNAME a `inbound.mailpace.com`). Si el webhook no responde 2xx, Mailpace reintenta 5 veces y después devuelve el correo al remitente.

## Decisión

- **Cuentas.** `CorreoCuenta` (una por usuario, `local` único en minúsculas) se crea la primera vez que el usuario abre el correo o le escriben. La parte local sale del `username`: minúsculas, sin acentos, y lo que no vale en una dirección se vuelve punto. Si dos usuarios chocan, el segundo lleva número. El dominio es `CORREO_DOMINIO` (por defecto `sistemafdn.com`).
- **Dominio de entrada.** `CORREO_DOMINIO_ENTRADA` (vacío: el mismo). Con un subdominio (`correo.sistemafdn.com`), lo que sale de aquí lleva `Reply-To: usuario@correo.sistemafdn.com`, así las respuestas entran por Mailpace aunque el MX del dominio raíz siga apuntando a otro proveedor. Al recibir se acepta la parte local en cualquiera de los dos dominios, y se ignora un `+etiqueta`.
- **Un mensaje por buzón.** `CorreoMensaje` es la copia de un buzón en una carpeta: Recibidos, Enviados, Borradores o Papelera (`carpetaPrevia` para restaurar). Lleva destacado, leído, `externo`, el estado del envío (`pendiente`/`enviado`/`fallido` con su error) y los encabezados del hilo (`Message-ID`, `In-Reply-To`, `References`). Mandar a otra cuenta de la casa no sale a internet: se le crea su copia en Recibidos en la misma transacción. Los adjuntos (`CorreoAdjunto`) de las copias comparten el archivo en disco (`CHAT_ARCHIVOS_DIR/correo`). El archivo se borra cuando ya no lo usa ninguna fila.
- **Borradores.** Al primer carácter el navegador crea el borrador (`POST /api/correo/borradores`). Después lo guarda con una pausa de 800 ms, en una fila: nunca dos guardados a la vez. Lo guarda también al cerrar el redactor y al salir de la página (`fetch` con `keepalive`). Responder, responder a todos y reenviar crean el borrador ya armado en el servidor (`Redaccion`): destinatarios, asunto con Re:/Rv:, la cita y los encabezados del hilo. Reenviar copia los adjuntos. Solo «Descartar» lo borra.
- **Quién escribe a quién.** Entre cuentas de la casa rige `Chat\Directorio::puedenConversar` (lo mismo que en el chat). Mandar fuera de la empresa pide la capacidad `correo.externo` (área Correo, ADR-039), que la migración da a `ROLE_ADMIN`. Recibir de fuera no pide nada.
- **Salida.** `EnviarCorreo` va a la cola `async`. El handler arma el `Email` (texto y HTML simple, adjuntos y encabezados del hilo) y lo manda con el transporte de `MAILER_DSN`, con un `Envelope` que lleva solo a los destinatarios de fuera. Marca la copia de Enviados como enviada o fallida y avisa al navegador. Un fallo se reintenta desde el correo (`POST …/reintentar`).
- **Entrada.** `POST /api/correo/entrante/mailpace` es público. Sin `CORREO_MAILPACE_CLAVE`, o con la firma mala, responde 401. Si el JSON no se puede leer, 400. Se entrega a cada cuenta de la casa que esté en To, Cc o Bcc (lo que Mailpace manda de cada uno). No se duplica por `Message-ID`, porque Mailpace puede reintentar. Si el correo no es para nadie de la casa, se responde 200 y no se hace nada, para que Mailpace no lo reintente.
- **Avisos.** Mercure por el tópico privado del chat (`tipo: correo`) y Web Push (`AvisosPush::avisar`) para lo que llega.
- **Lectura segura.** El HTML se muestra en un `iframe` con `sandbox` sin `allow-scripts` y con una CSP propia en el `srcdoc`. Las imágenes remotas están apagadas hasta «Mostrar imágenes». Los `cid:` se cambian por URLs firmadas que vencen (como las del chat). Los adjuntos se sirven con `nosniff`, y en línea solo se abren las imágenes y los PDF.
- **Frontend.** `core/correo/` (tipos, API, modelo puro y store) y `features/correo/`. El correo es una sección del chat: pestañas «Mensajes | Correo» en la barra, la misma ventana (esquina, flotante o centro, `/correo/:correo?`) y un solo contador en la cabecera. El diseño se acomoda al ancho del contenedor: tres columnas, dos o una.

## Consecuencias

- **Configuración externa** (no queda en el repositorio):
  - `MAILER_DSN=smtp://TOKEN:TOKEN@smtp.mailpace.com:465` en `.env.local` o en el entorno de producción. La sección `.env` lo explica.
  - En Mailpace, el dominio de entrada: un subdominio con CNAME a `inbound.mailpace.com`, y el webhook a `https://<host>/api/correo/entrante/mailpace`. La clave pública va en `CORREO_MAILPACE_CLAVE`.
  - Los workers de Messenger se reinician para tomar el mensaje nuevo.
- Mientras el MX de `sistemafdn.com` no apunte a Mailpace, lo que se escriba directamente a `usuario@sistemafdn.com` desde fuera no llega aquí. Llegan las respuestas, por el `Reply-To`.
- `post_max_size` sube a 40M (adjuntos de 10 MB cada uno, 25 MB por correo).
- Queda fuera de esta versión: acciones sobre varios correos a la vez, la vista por conversación, el redactor con formato y el vaciado automático de la Papelera.
