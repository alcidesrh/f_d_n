# Operativo — Ventas de Transporte (FDN)

Contexto que modela la operación de salidas de buses y la venta de pasajes:
geografía (enclaves y trayectos), flota (buses y asientos) y ventas (salidas y boletos).
IAM/seguridad y configuración de menús son contextos separados.

## Geografía

**Enclave**:
Un punto geográfico por donde pasa, para o sale un bus.
_Avoid_: lugar, punto

**Estacion**:
Un enclave donde los pasajeros abordan; es terminal de salida y de venta.
_Avoid_: terminal, estación rural

**Trayecto**:
Un servicio geográfico vectorial entre dos enclaves (origen → destino).
Es único por par de enclaves y puede estar compuesto de subtrayectos.
_Avoid_: ruta, servicio, tramo

**Subtrayecto**:
Un segmento de un trayecto compuesto: un trayecto hijo con posición dentro de su trayecto padre.
_Avoid_: tramo, subservicio, subruta

## Flota

**Bus**:
Un vehículo de la flota, perteneciente a una empresa, con una disposición de asientos por clase.
_Avoid_: unidad, vehículo

**Clase de bus** (`BusClase`):
El nivel de servicio del bus (Económica, Clase Oro, Platino, Starbus…). La tarifa depende de ella.
No confundir con la clase del asiento (A/B).
_Avoid_: gama, tipo de bus

**Asiento**:
Una plaza física de un bus, con una clase determinada, un número (consecutivo en el bus) y una celda en su croquis.
_Avoid_: puesto

**Croquis**:
El mapa de un bus visto desde arriba: una rejilla por planta (hasta dos) con coordenadas desde 1
—fila, columna— donde cada celda tiene como mucho un asiento, el chofer o una puerta.
En los buses de dos plantas, los asientos clase B (reclinables) van en la planta baja.
Se usa para editar el bus, mostrar la ocupación de un salida y elegir asientos al vender.
Los buses con la misma distribución comparten un **molde** (entidad `Croquis`): solo sirve
para elegir la distribución y luego un bus compatible al crear una salida. El molde no tiene
asientos (son de cada bus), no se edita y no hay dos iguales (ADR-027).

**Señal**:
Elemento del croquis que no se vende: el chofer (uno por bus) o una puerta.
Aquí "chofer" es el puesto de conducción dibujado en el croquis, no la persona (esa es el Piloto).

**Piloto**:
El conductor (o copiloto) asignado a un bus. La asignación es del bus, no del salida.
_Avoid_: conductor, chofer

## Ventas

**Salida**:
La salida concreta de un bus: un trayecto, una fecha y un bus.
Cada salida fija su ruta al crearse, resolviendo el trayecto de sus enclaves de origen y destino.
Estado (`EstadoSalida`): `programada → abordando → iniciada → finalizada` en ese orden;
`cancelada` solo es alcanzable desde `programada`.
_Avoid_: servicio, recorrido, itinerario, viaje

**Esquema de salidas**:
Una configuración guardada con un nombre para programar salidas: un trayecto, las horas del día
con su bus y cada cuántos días se repite. Es una plantilla: cambiarla no toca las salidas ya creadas.
_Avoid_: plantilla, horario

**Salidas idénticas**:
Salidas futuras programadas con el mismo trayecto, bus, empresa y hora; solo cambia el día.
Editar, anular o eliminar una salida puede propagarse a ellas, salvo a las que tienen asientos vendidos.

**BoletoAsiento**:
Un asiento vendido dentro de un salida, para un cliente y un trayecto
(puede ser un subtramo del salida completo), con precio y estado propios.
Único por `(asiento, trayecto, salida)`: el mismo asiento puede venderse dos veces
en el mismo salida solo si es para trayectos (subtramos) distintos.
Estado (`EstadoBoletoAsiento`): `emitido → chequeado → transito → finalizado` en ese orden;
`anulado` y `reasignado` solo son alcanzables desde `emitido`.
_Avoid_: boleto, ticket

**BoletoVenta**:
Una venta que agrupa uno o más boletos de asiento de un salida y un tramo, hecha por un canal,
facturada a un cliente y que puede emitir una factura.
Estado (`EstadoBoletoVenta`): `pendiente` (asientos apartados esperando la factura) → `confirmada`.
Facturación (`EstadoFacturacion`): `certificada`, `pendiente` (contingencia o fallo tras cobrar en la web)
o `no_aplica` (agencias, cortesías).
_Avoid_: venta, tiquetera

**Canal de venta**:
Por dónde entra una venta: `estacion` (taquilla, un usuario de la empresa), `agencia`
(usuario de una agencia, descuenta saldo, sin factura electrónica) o `web` (el propio cliente en la página).

**Tramo**:
La porción de un salida entre dos de sus paradas, por posición. Un asiento está ocupado para un tramo
si algún boleto vivo o alguna reserva vigente se solapa con él; tramos que no se solapan comparten asiento.

**Reserva** (precompra):
Asiento apartado en la página web mientras el cliente paga (`ReservaAsiento`). Se aparta al pulsar "Pagar asientos",
todo el carrito junto (ida y regreso) o nada. Vence sola a los 15 minutos (se extiende mientras el pago sigue) y nunca
después de 30 minutos antes de la salida; la venta en línea cierra antes (60 minutos por defecto). En taquilla se ve
como reservado.
_Avoid_: preventa, bloqueo

**Cortesía**:
Venta de asientos que no se cobran (el "voucher" del legado): total 0 y sin factura. Solo taquilla, con permiso.
_Avoid_: voucher, regalo

**Agencia**:
Entidad externa que vende boletos por comisión con usuarios propios. Cada venta descuenta su total del saldo,
que nunca queda negativo y solo cambia por movimientos (depósito, bonificación, venta, ajuste).
Sus ventas no llevan factura electrónica.
_Avoid_: estación tipo 4, punto de venta

**BoletoTarifa**:
El precio de referencia de un asiento. Rige desde su fecha de vigencia. Se aplica la más reciente del trayecto
y la clase de asiento cuyos demás campos coinciden o están vacíos, por prioridad: empresa, clase de bus,
horario, bus. Si ninguna cumple se deja de exigir el de menor prioridad (primero el bus, al final la empresa).
_Avoid_: tarifa, precio

**Factura**:
El documento fiscal (DTE) de una venta, certificado por el certificador FEL, con un snapshot inmutable
de emisor, receptor y certificador.
_Avoid_: recibo, comprobante

**Tipo de pago**, **Moneda**:
Forma y moneda en que el cliente paga una venta (el cobro con tarjeta en taquilla es en un POS externo).

**Cliente**:
La persona que compra un boleto de asiento (a quien se factura) o que viaja en él.
Puede tener documento de identificación (tipo y número) y nacionalidad.
_Avoid_: pasajero, comprador

**Empresa**:
La línea transportista dueña de la operación: buses, pilotos, salidas y tarifas.
_Avoid_: compañía, operador
