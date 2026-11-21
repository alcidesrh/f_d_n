# ADR-066: QR en lugar de código de barras en todo lo impreso

**Estado:** Aceptada (cambia lo impreso de [ADR-035](ADR-035-encomiendas.md) y de [ADR-063](ADR-063-chequeo-de-boletos-por-escaneo.md))

## Contexto

Lo impreso llevaba dos códigos: el QR de abordaje por asiento (ADR-063) y un código de barras Code 128
con el id de la venta (ticket de taquilla y PDF del boleto) o de la encomienda (recibo y guías). El QR se
lee con la cámara desde cualquier ángulo, también en la pantalla del celular, y aguanta papel dañado
(nivel M); el código de barras pide alinearlo y un lector láser no lee pantallas. En el boleto, además,
el código de barras se puede adivinar y chequea toda la venta de una vez.

## Decisión

- **Boleto** (ticket y PDF): solo los QR de abordaje. El número de la venta queda como texto («ID
  00000042»): es el número de compra que ve el cliente en la página y que se busca en taquilla. El campo
  `codigoBarras` de `DatosBoleto` conserva su nombre (lo usan la API y la página).
- **Encomienda** (recibo y guía de cada bulto): un QR con el mismo código de antes (el id con 8
  dígitos), con `frontend/src/shared/barcode/qr.ts`. Lo que lee el lector no cambia, así que embarcar,
  desembarcar, entregar y buscar siguen igual.
- Se quita `Code128` (backend y frontend): nada más lo dibujaba.
- La lectura sigue aceptando código de barras: el escaneo del abordaje pide `code_128` además de
  `qr_code` y acepta el id de la venta, y las pantallas de encomienda aceptan los dígitos que escriba el
  lector.

## Consecuencias

- Los boletos y las guías impresos antes de este cambio siguen leyéndose.
- Un lector USB **láser** (solo 1D) no lee QR. Las estaciones necesitan lectores de imagen (2D), que
  leen los dos, o la cámara.
- La guía ocupa lo mismo a lo largo: el QR mide 30 × 30 mm donde las barras medían 50 × 16 mm.
