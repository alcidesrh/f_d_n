# ADR-063: Chequeo de boletos por escaneo (QR del boleto y código de barras)

**Estado:** Aceptada (amplía [ADR-032](ADR-032-abordaje-y-chequeo-de-boletos.md))

## Contexto

El chequeo (ADR-032) se hacía marcando cada boleto en la lista de la salida. El boleto ya llevaba un
código de barras (Code 128 con el id de la venta), pero nada lo leía. Con el bus por salir, buscar a
cada pasajero en la lista es lento y se presta a marcar al que no es.

## Decisión

### Código de abordaje (`App\Venta\Boleto\LlaveBoleto`)

Cada boleto (`BoletoAsiento`) tiene un código `FDN1.<id>.<firma>`: la firma son 12 caracteres de un
HMAC-SHA256 del id con `kernel.secret`. No se guarda ni caduca: lo que decide si el boleto se chequea
es su estado y su salida. Un boleto reasignado es otro boleto, con otro código.

### QR en el PDF (`App\Venta\Boleto\CodigoQr`)

El PDF del boleto (web, correo y reimpresión) lleva un QR por asiento con ese código. El generador es
propio, sin dependencias, como `Code128`: modo byte, nivel M, versiones 1 a 6. Las medidas del SVG van
en píxeles porque Dompdf no escala bien con `viewBox`.

### Escaneo (`Abordaje::escanear`, `POST /api/abordaje/salidas/{id}/escaneo` `{ codigo }`)

- Un QR chequea su boleto. Un código de barras (solo dígitos: el id de la venta, el de los boletos
  impresos en taquilla) chequea los asientos de esa venta en esta salida que se puedan chequear aquí.
- Mismas reglas y permiso (`boleto.chequear`) que el chequeo manual: ventana de la salida, estación
  donde sube el pasajero y flujo del boleto. El escaneo solo marca presentes; «no se presentó» sigue
  siendo manual.
- Rechazos con código propio: `codigo_invalido`, `boleto_inexistente`, `otra_salida` (dice cuál),
  `ya_chequeado`, y los del flujo (anulado, reasignado, cancelado).

### Pantalla (`features/salida/EscanerDialog.vue`)

Botón «Escanear» en `/salidas/chequeo`. La cámara usa `BarcodeDetector` (Chrome y el WebView de
Android); un lector USB o el teclado escriben en el campo y terminan en Enter. Cada lectura responde en
verde o rojo, con vibración, y la cámara sigue leyendo; la misma lectura no se reenvía en 4 segundos.
La app móvil declara el permiso `CAMERA`.

## Consecuencias

- El código de barras de la venta se puede adivinar (es un número); el QR, no. Los boletos de taquilla
  siguen con código de barras hasta que el ticket impreso lleve el QR (pendiente: espejo del generador
  en `frontend/src/shared`).
- Donde el navegador no tiene `BarcodeDetector` (Safari, Firefox) queda el campo de texto.
- Si cambia `APP_SECRET`, los QR ya emitidos dejan de valer; esos boletos se chequean a mano.
