# ADR-067: Venta en varias salidas y encomienda fuera de la venta del boleto

**Estado:** Propuesta (rama `venta-multiples-salidas`; cambia la venta en taquilla de [ADR-021](ADR-021-venta-de-asientos.md) y la encomienda con boleto de [ADR-035](ADR-035-encomiendas.md))

## Contexto

En la taquilla, la selección de asientos era de una sola salida: cambiar de salida, de fecha o de
estaciones la borraba. Un cliente que compra la ida y la vuelta, o para varios días, pasaba por una venta
completa por cada salida.

La venta registraba además la encomienda que lleva el pasajero (modalidad `con_boleto`), en la misma
factura. En el legado la encomienda se registra en su propia pantalla, y así lo prefiere la operación.

## Decisión

- **Selección en varias salidas** (frontend, `features/venta/store.ts`): cada salida y tramo con asientos
  elegidos es un grupo (`GrupoVenta`) con sus asientos, pasajeros, «cobrar el trayecto completo» y su
  cotización. Cambiar de salida, fecha o estaciones conserva los grupos; volver a una salida muestra sus
  asientos elegidos. Encima de «Cortesía» y «Facturar» va cada asiento elegido con su precio y la ✕ para
  quitarlo (`AsientosElegidos.vue`); con varias salidas, el tooltip dice la fecha y el viaje. La
  ocupación de las otras salidas elegidas también se oye por Mercure: si otro vendedor toma un asiento, se
  quita de su grupo.
- **Una venta por salida**, sin cambiar el modelo ni el backend: el cobro manda un `POST /api/venta/ventas`
  por grupo, uno tras otro, cada uno con su `token`. Cada venta es un `BoletoVenta` con la factura de la
  empresa de su salida y su ticket. Para la gestión interna son compras separadas del mismo cliente.
- **Si una falla**, se detiene ahí: las anteriores quedan vendidas (una factura certificada no se deshace)
  y esa y las siguientes siguen elegidas. Si fue el certificador, reintentar sigue desde esa con la misma
  clave (la venta fallida se borró, así que no se repite); «sin factura electrónica» aplica solo a esa.
  Cancelar las deja elegidas con claves nuevas.
- **Encomienda fuera de la venta**: `SolicitudVenta` ya no recibe `encomiendas`, `RegistroVenta` no las
  registra y `/api/venta/contexto` no ofrece su catálogo; la pantalla de venta no tiene la sección. La
  encomienda se registra en `/encomiendas/registrar`.

## Consecuencias

- Con N salidas salen N facturas al mismo cliente, aunque sean de la misma empresa. Juntarlas en un
  `BoletoVenta` rompería el «una venta, un viaje» del ticket, la reasignación y la anulación.
- El cobro tarda lo que tarda el certificador por cada salida (en serie).
- Lo que ya existe de `con_boleto` se conserva: la modalidad, la relación `BoletoVenta.encomiendas`, su
  renglón en la factura (refacturar o certificar pendientes), su anulación con la venta y su línea en el
  ticket de una reimpresión. Quitarlo pide una migración y decidir qué pasa con las ya registradas.
- La reasignación sigue siendo de una sola salida: al iniciarla se vacía la selección.
