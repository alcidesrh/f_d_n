# ADR-066: Tipos de pasajero y promociones en la venta de taquilla y agencia

**Estado:** Aceptada (sobre la tarifa de [ADR-021](ADR-021-venta-de-asientos.md))

## Contexto

Todo asiento se cobraba a la tarifa (`BoletoTarifa`). Los descuentos de niño, tercera edad o
estudiante se hacían a mano o con cortesías, y no había forma de lanzar una promoción por fechas
ni de saber después cuánto se descontó ni por qué.

## Decisión

**Tipos de pasajero** (`TipoPasajero`: nombre, porcentaje, requisito, activo). Se eligen por asiento
en la pantalla de venta; sin tipo, el asiento paga la tarifa completa. El requisito («Presentar
carné») se le recuerda a la taquilla.

**Promociones** (`Promocion`: nombre, porcentaje, fechas de viaje `desde`–`hasta`, empresa, ruta y
clase de bus opcionales, código opcional, activa). Valen para las salidas cuya fecha cae en la
vigencia y que coinciden con lo que fijen. Sin código se aplican solas; con código, solo si la venta
lo escribe. Un código que no existe o no vale para la salida rechaza la cotización
(`codigo_promocion`).

**Reglas** (`App\Venta\Descuento\ReglasDescuento`, puro, con tests):

- Los descuentos **no se suman**: cada asiento lleva el porcentaje más alto entre su tipo y las
  promociones que valen. Empatados, el tipo de pasajero.
- El importe se redondea al centavo; la mitad, a favor del pasajero.
- La cortesía no lleva descuento (no se cobra).

**Dónde se aplica.** `Descuentos::aplicar` sobre la cotización de `ReglasVenta::cotizar`, en
`POST /api/venta/cotizacion` (con `tiposPasajero` por asiento y `codigoPromocion`) y en
`RegistroVenta` (`asientos[].tipoPasajero`, `codigoPromocion`). Solo en taquilla y agencias: la venta
en línea sigue a la tarifa más el recargo de la página.

**Qué queda en el boleto.** `BoletoAsiento.precio` es lo cobrado; `descuento` (centavos), el
`motivoDescuento` («Niño 50 %», queda aunque el tipo cambie o se borre), `tipoPasajero` y
`promocion`. La tarifa es `precio + descuento` (`getPrecioLista`). La factura lleva el precio ya
rebajado.

**Reasignación.** Se compara la tarifa del asiento nuevo con la del boleto (no con lo cobrado), y
el boleto nuevo hereda el descuento.

**Dónde se ve.** En la venta, la tarifa tachada y el motivo por asiento y el total descontado; en el
ticket y el PDF del boleto, el motivo y el importe; en el detalle de factura de boletos (PDF y
Excel), en la descripción.

## Consecuencias

- Elegir un tipo de pasajero no pide un permiso aparte: lo hace quien vende. Los reportes muestran
  cada descuento para revisarlo.
- La venta en línea no tiene tipos de pasajero ni código promocional todavía: pide guardar el código
  en el pago (`PagoWeb`) y volver a aplicarlo al registrar la compra, en los cinco idiomas de la
  página.
- No hay tope de usos por código ni por cliente.
- Los boletos de antes quedan con descuento 0.
