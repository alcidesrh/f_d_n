# Operativo — Ventas de Transporte (FDN)

Contexto que modela la operación de salidas de buses y la venta de pasajes:
geografía (enclaves, rutas y trayectos), flota (buses y asientos) y ventas (salidas y boletos).
IAM/seguridad, menús y chat tienen sus propias secciones al final.

Cada término indica su modelo: `nuevo` = entidad viva en `backend/src/Entity/`; `legacy` = solo existe en `backend/src/EntitySistemaFdn/` (sistema TerminalOmnibus), sin equivalente todavía en el modelo nuevo; `concepto` = término de negocio vigente sin entidad dedicada (absorbido por otra entidad o pendiente de modelar).

## Geografía

**Enclave**:
Un punto geográfico por donde pasa, para o sale un bus.
_Avoid_: lugar, punto
Modelo: nuevo. Detalle del modelo: Ubicacion geografica de interes para el negocio. Inmuebles, sitios y emplazamientos geolocalizables relacionados con el negocio. Tiene `departamento` (la página web agrupa orígenes y destinos por él).
Evitar (modelo): lugar, punto

**Estacion**:
Un enclave donde los pasajeros abordan; es terminal de salida y de venta.
_Avoid_: terminal, estación rural
Modelo: nuevo. Detalle del modelo: Enclaves que cumplen diferentes funciones como venta de boletos en taquilla, salida o destino de los buses y demás.
Evitar (modelo): terminal

**Trayecto**:
El par comercial vectorial entre dos enclaves (origen → destino): lo que pide el cliente,
lo que lleva tarifa y lo que se factura. Es único por par de enclaves. No describe por dónde
pasa el bus (eso es la ruta): el mismo trayecto puede viajarse en salidas de rutas distintas.
_Avoid_: ruta, servicio, tramo, subtrayecto
Modelo: nuevo. Detalle del modelo: Par comercial vectorial entre dos enclaves: lo que pide el cliente, lo que lleva tarifa (`BoletoTarifa`) y lo que se factura. A→B y B→A son distintos. Único por par `(origen, destino)`. No describe el camino del bus (eso es la `Ruta`, ADR-030); existe el de cada par de paradas en orden de cada ruta. Un tramo intermedio de una ruta es un trayecto más (lo que antes se llamaba subtrayecto).
Evitar (modelo): ruta, salida

**Ruta**:
El camino que recorre un bus: una secuencia ordenada de paradas, de la primera (origen) a la
última (destino). No depende de la empresa ni de la fecha. Cualquier par de paradas en orden
es un trayecto que se puede vender en ella (si tiene tarifa). Varias rutas pueden unir las
mismas puntas por caminos distintos. Equivale a `ruta` del legado (ADR-030).
_Avoid_: itinerario (en el legado es la plantilla de horarios), recorrido
Modelo: nuevo. Detalle del modelo: Camino que recorre un bus: secuencia ordenada de `Parada` (primera = origen, última = destino), sin empresa ni fecha. Cada par de paradas en orden es un trayecto vendible si tiene tarifa. Varias rutas pueden unir las mismas puntas. Equivale a `ruta` + `ruta_estacion_item` + `tiempo` del legado (ADR-030).
Evitar (modelo): itinerario (en el legado es la plantilla de horarios), recorrido

**Parada**:
El paso de una ruta por un enclave, con su orden, los minutos estimados desde el origen
(por clase de bus cuando difieren) y si ahí se puede subir y bajar.
_Avoid_: punto de parada, escala
Modelo: nuevo. Detalle del modelo: Paso de una `Ruta` por un enclave: `orden` (0 = origen), minutos desde el origen (`ParadaTiempo` por clase de bus cuando difieren), km y si se puede subir/bajar. Ver ADR-030.
Evitar (modelo): punto de parada, escala

## Flota

**Bus**:
Un vehículo de la flota, perteneciente a una empresa, con una disposición de asientos por clase.
_Avoid_: unidad, vehículo
Modelo: nuevo. Detalle del modelo: Vehículo de la flota con disposición de asientos. Tiene `piloto` y `copiloto` (tripulación habitual asignada al bus, ambos `ManyToOne` a `Piloto`; `copiloto` se llamó `pilotoAux` hasta ADR-016).
Evitar (modelo): unidad, vehículo, pilotoAux

**Clase de bus** (`BusClase`):
El nivel de servicio del bus (Económica, Clase Oro, Platino, Starbus…). La tarifa depende de ella.
No confundir con la clase del asiento (A/B).
_Avoid_: gama, tipo de bus
Término de la tabla: **Clase de bus** (`BusClase`)
Modelo: nuevo. Detalle del modelo: Nivel de servicio del bus (Económica, Clase Oro, Platino, Starbus…): `Bus.clase`. Decide la tarifa. En el legado `bus_clase`, que el bus tenía por su tipo (`bus_tipo.clase_id`). No es la clase del asiento (A/B).
Evitar (modelo): gama, tipo de bus

**Asiento**:
Una plaza física de un bus, con una clase determinada, un número (consecutivo en el bus) y una celda en su croquis.
_Avoid_: puesto
Modelo: nuevo. Detalle del modelo: Asiento de bus. Estan enumerados y tienen coordenadas con respecto al bus de su ubicacion. Hay dos clase A y B segun sus prestaciones siendo B la de mejor confort y mas cara.
Evitar (modelo): boleto, ticket

**Croquis**:
El mapa de un bus visto desde arriba: una rejilla por planta (hasta dos) con coordenadas desde 1
—fila, columna— donde cada celda tiene como mucho un asiento, el chofer o una puerta.
En los buses de dos plantas, los asientos clase B (reclinables) van en la planta baja.
Se usa para editar el bus, mostrar la ocupación de un salida y elegir asientos al vender.
Los buses con la misma distribución comparten un **molde** (entidad `Croquis`): sirve para
elegir la distribución y luego un bus compatible al crear una salida. El molde fija número y
clase de cada asiento: el bus de una salida con boletos se puede cambiar por otro del mismo
molde y los boletos pasan al mismo número. El molde no tiene asientos (son de cada bus), no
se edita y no hay dos iguales (ADR-027, ADR-031).
Modelo: nuevo. Detalle del modelo: Mapa del bus visto desde arriba: sus `Asiento` + sus `BusSenal`, cada uno en una celda `(planta, fila, columna)` con coordenadas desde 1 (el legado usaba múltiplos de 50: `X / 50 + 1`). Hasta dos plantas (`planta` 1 = baja, 2 = alta; en los de dos pisos la clase B va abajo). Se edita entero por `PUT /api/buses/{id}/croquis` (ver ADR-019). Los buses con la misma distribución comparten un **molde** (entidad `Croquis`, `Bus.croquis`): solo filtra buses al programar una salida; no tiene asientos, no se edita ni se duplica, y el croquis de un bus no cambia mientras tenga salidas activas (ADR-027).
Evitar (modelo): mapa del bus, lienzo, distribución

**Señal**:
Elemento del croquis que no se vende: el chofer (uno por bus) o una puerta.
Aquí "chofer" es el puesto de conducción dibujado en el croquis, no la persona (esa es el Piloto).
Término de la tabla: **Señal** (`BusSenal`)
Modelo: nuevo. Detalle del modelo: Elemento del croquis que no se vende: chofer (uno por bus) o puerta (`TipoBusSenal`). "Chofer" aquí es el puesto de conducción dibujado, no la persona (esa es `Piloto`). En el legado, `bus_senal`/`bus_senal_tipo`.

**Piloto**:
El conductor (o copiloto) asignado a un bus. La asignación es del bus, no del salida.
_Avoid_: conductor, chofer
Modelo: nuevo. Detalle del modelo: Conductor (o copiloto) asignado a un bus. No tiene relación con `Salida` (ver ADR-016).
Evitar (modelo): conductor, chofer

## Ventas

**Salida**:
La salida concreta de un bus: una ruta, una fecha (la hora en la primera parada) y un bus.
Estado (`EstadoSalida`): `programada → abordando → iniciada → finalizada` en ese orden;
`cancelada` solo es alcanzable desde `programada`.
_Avoid_: servicio, recorrido, itinerario, viaje
Modelo: nuevo. Detalle del modelo: Viaje concreto de un bus: ruta (ADR-030) + fecha (hora en la primera parada) + bus (siempre: no hay salidas sin bus, ADR-027; con boletos se cambia por otro del mismo molde y pasan por número, ADR-031) + lista de BoletoAsiento vendidos. Nombre de clase renombrado desde `Servicio` → `Recorrido` (ADR-013) → `Salida` (ADR-022); no confundir con el `Recorrido`-plantilla, ya eliminado (ver ADR-011). Sin relación con `Piloto` — la tripulación es del `Bus` (ver ADR-016). Estado tipado con enum `EstadoSalida`: `programada → abordando → iniciada → finalizada` (lineal), `cancelada` solo desde `programada` (ver ADR-014).
Evitar (modelo): servicio, recorrido, itinerario

**Esquema de salidas**:
Una configuración guardada con un nombre para programar salidas: una ruta, las horas del día
con su bus y cada cuántos días se repite. Es una plantilla: cambiarla no toca las salidas ya creadas.
Un esquema **automático** lo mantiene programado el sistema unos días hacia adelante (90 por defecto):
cada madrugada crea las salidas que faltan. Lo ya programado no se vuelve a crear aunque se anule o borre.
_Avoid_: plantilla, horario
Término de la tabla: **Esquema de salidas** (`EsquemaSalida`)
Modelo: nuevo. Detalle del modelo: Configuración guardada con un nombre desde el programador: ruta, horas del día con su bus y cada cuántos días se repite (sin fechas). Es una plantilla: cambiarla o borrarla no toca las salidas ya creadas (ADR-024). Automático (ADR-044): `automatico`, `horizonteDias`, `ancla` (día desde el que cuenta el intervalo), `programadoHasta` (frontera: lo de antes no se vuelve a desplegar) y pausa (`pausadoMotivo`) cuando la ruta se inactiva o un bus deja de ser de la empresa.
Evitar (modelo): plantilla, horario

**Horario de la salida** (`SalidaParada`):
A qué hora pasa una salida por cada parada de su ruta. Se fija al crear la salida (y al cambiar
su fecha, hora o bus); la de cada parada intermedia es estimada. Las salidas con boletos lo
conservan aunque se edite la ruta, así un boleto nunca cambia de tramo.
_Avoid_: itinerario
Término de la tabla: **Horario de la salida** (`SalidaParada`)
Modelo: nuevo. Detalle del modelo: Hora (exacta en el origen, estimada en las demás) a la que una salida pasa por cada parada de su ruta. Foto que se fija al crear la salida o cambiar fecha, hora o bus; las salidas con boletos la conservan aunque se edite la ruta (ADR-030).
Evitar (modelo): itinerario

**Salidas idénticas**:
Salidas futuras programadas con la misma ruta, bus, empresa y hora; solo cambia el día.
Editar, anular o eliminar una salida puede propagarse a ellas, salvo a las que tienen asientos vendidos.
Modelo: concepto. Detalle del modelo: Salidas futuras programadas con la misma ruta, bus, empresa y hora del día; solo cambia el día. A ellas se propaga editar, anular o eliminar (`App\Salida\FirmaSalida`, ADR-024).

**BoletoAsiento**:
Un asiento vendido dentro de una salida, para un cliente y un tramo de su ruta,
con el trayecto que se le cobró, precio y estado propios.
El mismo asiento se vende varias veces en la misma salida solo para tramos que no se solapan.
Estado (`EstadoBoletoAsiento`): `emitido → chequeado → transito → finalizado` en ese orden;
`anulado` y `reasignado` solo son alcanzables desde `emitido`.
_Avoid_: boleto, ticket
Modelo: nuevo. Detalle del modelo: Asiento vendido dentro de una salida para un trayecto (el par que viaja, que puede ser un tramo intermedio de la ruta) y su tramo `[ordenSube, ordenBaja)` por orden de las paradas (ADR-030). Dos boletos vivos del mismo asiento y salida no se solapan: restricción de exclusión `ex_boleto_asiento_tramo` (solo en migraciones; Doctrine la ignora). Estado tipado con enum `EstadoBoletoAsiento`: `emitido → chequeado → transito → finalizado` (lineal; `emitido → transito` si se chequea con la salida ya iniciada); `anulado`/`reasignado`/`cancelado`/`pospuesto` solo alcanzables desde `emitido` (`cancelado` también desde `chequeado`, y vuelve a `emitido` al revertirlo; `pospuesto` solo pasa a `reasignado` o `anulado`; ver ADR-014, ADR-032, ADR-033). Los que liberan el asiento: `EstadoBoletoAsiento::liberanAsiento()`. El estado solo cambia por una transición con nombre del workflow `boleto_asiento` (`TransicionBoleto`, `FlujoBoleto`, ADR-037).
Evitar (modelo): boleto, ticket

**BoletoVenta**:
Una venta que agrupa uno o más boletos de asiento de un salida y un tramo, hecha por un canal,
facturada a un cliente y que puede emitir una factura.
Estado (`EstadoBoletoVenta`): `pendiente` (asientos apartados esperando la factura) → `confirmada`.
Facturación (`EstadoFacturacion`): `certificada`, `pendiente` (contingencia o fallo tras cobrar en la web)
o `no_aplica` (agencias, cortesías).
_Avoid_: venta, tiquetera
Modelo: nuevo. Detalle del modelo: Agrupa boletos, usuario quien lo emite, cliente, factura tributaria. Tiene canal (`CanalVenta`: `estacion`, `agencia`, `web`), estado (`pendiente` → `confirmada`) y estado de facturación (`certificada`, `pendiente`, `no_aplica`). Solo se crea por `App\Venta` (ver ADR-021); la API la expone solo para lectura.
Evitar (modelo): venta, tiquetera

**Canal de venta**:
Por dónde entra una venta: `estacion` (taquilla, un usuario de la empresa), `agencia`
(usuario de una agencia, descuenta saldo, sin factura electrónica) o `web` (el propio cliente en la página).

**Tramo**:
La porción de una salida entre dos paradas de su ruta, por orden: `[sube, baja)`. Un asiento está
ocupado para un tramo si algún boleto vivo o alguna reserva vigente se solapa con él; tramos que no se
solapan comparten asiento. En taquilla se puede cobrar el trayecto completo de la ruta aunque el pasajero
viaje un tramo: el tramo ocupado no cambia.
Modelo: concepto. Detalle del modelo: Porción de una salida entre dos paradas de su ruta, por orden: `[sube, baja)` (`BoletoAsiento.ordenSube/ordenBaja`, ADR-030). Un asiento se revende en tramos que no se solapan (`App\Venta\Ocupacion`). Lo ocupado (tramo) puede ser menos que lo cobrado (trayecto).

**Reserva** (precompra):
Asiento apartado en la página web mientras el cliente paga (`ReservaAsiento`). Se aparta al pulsar "Pagar asientos",
todo el carrito junto (ida y regreso) o nada. Vence sola a los 15 minutos (se extiende mientras el pago sigue) y nunca
después de 30 minutos antes de la salida; la venta en línea cierra antes (60 minutos por defecto). En taquilla se ve
como reservado.
_Avoid_: preventa, bloqueo
Término de la tabla: **Reserva** (`ReservaAsiento`)
Modelo: nuevo. Detalle del modelo: Asiento apartado en la página web mientras el cliente paga (la "precompra"). Se aparta al pulsar "Pagar asientos", todo el carrito junto (ida y regreso) o nada. Vence sola a los 15 min (se extiende mientras paga) y nunca después de 30 min antes de la salida (ADR-023).
Evitar (modelo): preventa

**Cortesía**:
Venta de asientos que no se cobran (el "voucher" del legado): total 0 y sin factura. Solo taquilla, con permiso
(o con una autorización).
_Avoid_: voucher, regalo
Modelo: nuevo (`BoletoVenta.cortesia`). Detalle del modelo: Venta sin cobro ni factura (el "voucher" del legado para boletos), con permiso `venta.cortesia`.
Evitar (modelo): voucher

**Autorización** (`SolicitudAutorizacion`):
Un pase de un solo uso para que un usuario haga, sobre registros concretos, lo que una regla le impide: anular o
reasignar boletos sin el permiso, pasado su límite diario o con la salida ya pasada de hora; emitir cortesías sin
el permiso o pasado su límite. La pide quien quedó bloqueado, con un motivo; la aprueba o la rechaza quien aprueba
autorizaciones donde cae la operación; se usa sola al repetir la operación y vence a las 24 horas.
Estado: `pendiente → aprobada → usada`, o `rechazada`, `retirada`, `vencida`.
_Avoid_: pase, permiso temporal, excepción
Término de la tabla: **Autorización** (`SolicitudAutorizacion`)
Modelo: nuevo. Detalle del modelo: Pase de un solo uso para lo que una regla le impide a un usuario: hacerlo sin el permiso, pasado su límite diario o fuera de hora (`Dispensa`), sobre registros concretos (boletos de una venta o una salida). La pide quien quedó bloqueado, la aprueba o rechaza quien tiene `autorizacion.aprobar` donde cae la operación, y se usa sola al repetir la operación. Vence a las 24 h. Ver ADR-040.
Evitar (modelo): pase, permiso temporal

**Agencia**:
Entidad externa que vende boletos por comisión con usuarios propios. Cada venta descuenta su total del saldo,
que nunca queda negativo y solo cambia por movimientos (depósito, bonificación, venta, ajuste).
Sus ventas no llevan factura electrónica.
_Avoid_: estación tipo 4, punto de venta
Modelo: nuevo. Detalle del modelo: Entidad externa que vende por comisión con usuarios propios (`Usuario.agencia`). Cada venta descuenta su saldo (nunca negativo), sin factura electrónica. El saldo solo cambia por `AgenciaMovimiento`. En el legado era una estación tipo 4; si además era parada de una ruta (Belice City, Chetumal), su lugar también es un `Enclave` (ADR-030).
Evitar (modelo): estación tipo 4

**BoletoTarifa**:
El precio de referencia de un asiento. Rige desde su fecha de vigencia. Se aplica la más reciente del trayecto
y la clase de asiento cuyos demás campos coinciden o están vacíos, por prioridad: empresa, clase de bus,
horario, bus. Si ninguna cumple se deja de exigir el de menor prioridad (primero el bus, al final la empresa).
_Avoid_: tarifa, precio
Modelo: nuevo. Detalle del modelo: Precio de referencia de un BoletoAsiento. Se aplica la más reciente (`vigenteDesde`, ya vigente) del trayecto y la clase de asiento cuyos demás campos coinciden o están vacíos; por prioridad: empresa > clase de bus > horario (`horaDesde`–`horaHasta`, puede cruzar la medianoche) > bus. Si ninguna cumple se deja de exigir el de menor prioridad, y así sucesivamente (trayecto y clase de asiento nunca). Resolver: `App\Venta\EspecificidadTarifa`, ADR-021. Las del legado no tienen empresa.
Evitar (modelo): tarifa, precio

**Factura**:
El documento fiscal (DTE) de una venta, certificado por el certificador FEL, con un snapshot inmutable
de emisor, receptor y certificador.
_Avoid_: recibo, comprobante
Modelo: nuevo. Detalle del modelo: Documento fiscal con snapshot inmutable de emisor/receptor
Evitar (modelo): recibo, comprobante

**Tipo de pago**, **Moneda**:
Forma y moneda en que el cliente paga una venta (el cobro con tarjeta en taquilla es en un POS externo).
Término de la tabla: **Tipo de pago**, **Moneda**, **Tipo de documento**
Modelo: nuevo. Detalle del modelo: Catálogos de la venta (`TipoPago`, `Moneda`, `TipoDocumento`), migrados del legado.

**Cliente**:
La persona que compra un boleto de asiento (a quien se factura) o que viaja en él.
Puede tener documento de identificación (tipo y número) y nacionalidad.
_Avoid_: pasajero, comprador
Modelo: nuevo. Detalle del modelo: Persona que compra un boleto. Son los pasajeros.
Evitar (modelo): pasajero, comprador

**Empresa**:
La línea transportista dueña de la operación: buses, pilotos, salidas y tarifas.
_Avoid_: compañía, operador
Modelo: nuevo. Detalle del modelo: Línea transportista dueña de la operación
Evitar (modelo): compañía, operador

## Encomiendas

**Encomienda**:
Envío de paquetería en la bodega de los buses: remitente → destinatario, de un enclave a otro, con uno o más artículos (`EncomiendaArticulo`: paquete por peso o especial del catálogo). Modalidad `pagada` (factura al recibir), `por_cobrar` (factura al entregar), `interna` (sin cobro) o `con_boleto` (la lleva un pasajero y va en la factura de su venta). Estado `recibida → embarcada → transito → desembarcada → entregada` (transbordo: desembarcada → embarcada), `anulada` si no ha viajado. Cambia solo por una transición con nombre del workflow `encomienda` (`TransicionEncomienda`, `FlujoEncomienda`, ADR-038). Ver ADR-035.
_Avoid_: paquete, envio
Modelo: nuevo
**Bulto** / **Guía**:
Cada unidad física de una encomienda lleva una guía impresa («Nro k de N») con el código de barras de la encomienda (su id, 8 dígitos) y la sigla del destino (`Enclave.alias`, GUA, SEP).
_Avoid_: etiqueta
Modelo: concepto
**Especial** (`EncomiendaEspecial`):
Tipo de encomienda con precio fijo por unidad (sobre, llanta, bicicleta…). Algunos no se envían por cobrar.
_Avoid_: tipo especial
Modelo: nuevo
**Tarifa de encomienda**:
Paquete: `TarifaEncomiendaPeso` (más de X hasta Y lb) + recargo `TarifaEncomiendaDistancia` (par de enclaves, cualquier sentido); especial: su precio. Descuento por bultos de la encomienda: 3–5 → 5 %, 6–9 → 7 %, 10+ → 10 % (`App\Encomienda\Cotizador`, como el legado).
Modelo: nuevo

## Ventas (continuación)

**Recargo web** (`ConfiguracionPagina.recargoPorciento`):
Porciento que la página web suma a la tarifa (el `compra_porciento` del legado). Se configura en el dashboard; un pago en curso conserva el que tenía (`PagoWeb.recargoPorciento`).
Modelo: nuevo
**Depósito de agencia** (`AgenciaDeposito`):
Boleta de depósito bancario de una agencia: se registra sin tocar el saldo y un supervisor la acredita (saldo + bonificación por `SaldoAgencia`) o la rechaza con motivo. Una boleta no se repite en la agencia salvo rechazada (ADR-034).
_Avoid_: depósito, recarga
Modelo: nuevo
**Voucher**:
Es un boleto o encomienda que no se cobra. La razon puede ser desde desicion administrativa hasta restitucion de un por un viaje cancelado. Para boletos del legado se migra como `BoletoVenta.voucher` (`app:venta:vouchers-legado` en bases ya migradas); los nuevos se emiten como Cortesía.
Modelo: nuevo (`BoletoVenta.voucher`, solo legado; para boletos nuevos: ver Cortesía)
**Pospuesto**:
Boleto de una compra web pospuesta desde el dashboard ("Posponer"): libera el asiento, conserva la venta, su factura y su importe. Se reanuda reasignándolo a otra salida de la misma empresa (o se anula), aunque su salida original ya haya pasado (`EstadoBoletoAsiento::POSPUESTO`, `App\Venta\EnLinea\GestionCompraWeb`).
Modelo: nuevo
**Cancelado** (no se presentó):
Boleto cuyo pasajero no se presentó a abordar (NO en el chequeo, emitido en el origen al iniciar la salida, o emitido sin chequear al finalizarla). Libera el asiento pero conserva la venta, su factura y su importe: no es una anulación. Es el "Cancelado" (6) del legado (ADR-032). También se cancela desde el listado de boletos un emitido o chequeado ("Cancelar", ADR-033), y desde ahí se revierte a emitido si el asiento sigue libre ("Revertir").
_Avoid_: anulado
Modelo: nuevo (`EstadoBoletoAsiento::CANCELADO`)
**Chequeo** (abordaje):
Marcar al abordar cada boleto de una salida como presente (`chequeado`, o `transito` si la salida ya inició) o no presentado (`cancelado`). Lo hace la estación donde sube el pasajero, con la salida abordando o iniciada (ADR-032).
_Avoid_: check-in
Modelo: concepto
**Reasignacion**:
Cambio de asiento (de la misma salida o de otra) de boletos emitidos, antes de la hora de salida (después, con una autorización fuera de hora, ADR-040) y al mismo precio. El boleto original pasa a `reasignado` y el nuevo queda en la misma venta con `reasignadoDe`. Solo entre salidas de la misma empresa, también lo vendido en la página (`App\Venta\Reasignacion`, ADR-028). Un boleto pospuesto se reasigna aunque su salida ya haya pasado: así se reanuda.
Modelo: nuevo
**Anulacion**:
Cuando se invalida la compra de un asiento asi como su registro en la oficina tributaria: el boleto pasa a `anulado` (asiento libre), la factura se anula en Forcon (cubre toda la venta; si era conjunta y quedan boletos, se certifica otra con ellos) y a una agencia se le devuelve el precio. Solo antes de la hora de salida, salvo con una autorización (`App\Venta\Anulacion`, ADR-028, ADR-040).
Modelo: nuevo
**Bitácora**:
Traza de solo agregar de una `Salida` o un `BoletoAsiento`: operación (boleto: creado, anulado, reasignado, chequeado, tránsito, finalizado, cancelado, revertido; salida: creada, abordando, iniciada, finalizada, cancelada, cambio de bus, eliminada), usuario, fecha y detalle. La escribe un listener de Doctrine en la misma transacción (`App\Bitacora`, ADR-028).
_Avoid_: log, historial, auditoría
Modelo: nuevo (`Bitacora`)
**Manifiesto\<de pasajero, de venta, ...\>**:
son reportes que se generan en formato pdf. Ya existen el **interno** (por lugar de emisión, con importes) y el **del piloto** (por asiento, con nacionalidad y firmas), desde "Ver/PDF" en `/salidas` (`GET /api/gestion-salidas/{id}/manifiesto/{interno|piloto}`, permiso `salida.ver`); los demás siguen en el legado.
Modelo: nuevo (interno, piloto) / legacy (resto)

## Usuarios y accesos

**Usuario**:
Empleado de alguna empresa. Usan el sistema para la venta de boletos o encomiendas, crean el calendarios de salida, generan reportes y demas procesos del negocio segun el rol asignado
Modelo: nuevo
**Capacidad**:
Algo que el código permite hacer y exige con `isGranted()` (`venta.vender`, `boleto.anular`, `bus.editar`). El catálogo vive en el código: `App\Acceso\Capacidad` (dominio) y `#[Recurso]` de cada entidad (CRUD genérico: `{entidad}.ver|editar|eliminar`). Cada una dice si se acota por empresa, por estación o por nada, y qué límites admite (ADR-039).
_Avoid_: permiso, acción
Modelo: nuevo (código)
**Rol** (`Role`):
Puesto con nombre: el conjunto de capacidades que concede y sus límites. Plano (no hereda). El de sistema (`sistema`) tiene todas; `ROLE_USER` es el rol base, lo tienen todos sin asignárselo y solo sirve para menús y administración (ADR-039).
_Avoid_: perfil, grupo
Modelo: nuevo
**Asignación** (`Asignacion`):
Un rol concedido a un usuario sobre un alcance —empresas × estaciones, cada una un conjunto o «todas» (también las futuras)— y, si es temporal, con vigencia (`desde`/`hasta`). Lo que puede un usuario es la unión de sus asignaciones vigentes: sin negaciones ni excepciones por usuario. A un usuario de agencia el alcance se lo da su agencia (`Agencia.empresas`) (ADR-039).
_Avoid_: permiso directo
Modelo: nuevo
**Alcance**:
Dónde rige una capacidad para un usuario: la unión de los ámbitos de sus asignaciones que la conceden (`App\Acceso\Alcance`). Un registro sin empresa (compartido) solo lo cubre «todas las empresas» (ADR-039).
_Avoid_: tenant
Modelo: concepto
**Estación de trabajo**:
Dónde opera ahora el usuario (`Usuario.estacion`): ahí vende, chequea y recibe. La elige entre las estaciones de sus asignaciones (cabecera; si tiene una sola, queda fija). Las estaciones son compartidas entre empresas (ADR-039).
_Avoid_: estación del usuario
Modelo: concepto

## Menús, taxonomía y chat

**Taxonomía**:
Jerarquía ordenada (árbol, una o más raíces) sobre registros de cualquier entidad, por referencia polimórfica `(subjectClass, subjectId)`. Un mismo registro puede estar en varias taxonomías. Ver ADR-018.
_Avoid_: categoría, árbol
Modelo: nuevo
**Menú**:
Taxonomía de ítems navegables visible para quien tiene asignado alguno de sus roles (el rol base lo ven todos, el de sistema ve todo; sin jerarquía, ADR-039) y colocada en una o más áreas de la UI (`MenuPlacement`, `LayoutArea`). Cada ítem se muestra solo si el usuario puede abrir su pantalla.
_Avoid_: navegación
Modelo: nuevo
**Ítem navegable** (`MenuItem`):
Texto + ícono + destino: una ruta de vue-router (`VueRoute`), un **enlace** (`path` escrito a mano, p. ej. `/salidas/chequeo`) o ninguno, y entonces es un **grupo**: agrupa a sus hijos y no navega. Se crea antes de usarse y puede estar en varios menús. El orden y el nivel viven en cada menú, no en el ítem.
_Avoid_: opción
Modelo: nuevo
**Conversación** (`ChatCanal`):
Chat interno: `directo` (dos usuarios, una por par) o `grupo` (con nombre). Una agencia solo conversa con su gente y con la administración (`App\Chat\Directorio`, ADR-026). Un grupo tiene **dueño** (quien lo creó, lo edita) y es **abierto** (todos escriben) o **dirigido** (todos reciben; escriben el dueño y los **emisores** que él elige). Un grupo abierto puede ser **grupo miembro** de otro (`ChatSubgrupo`): sus miembros llegan "por" él (`via`).
_Avoid_: grupo de WhatsApp
Modelo: nuevo
**Tarjeta** (chat):
Registro compartido en un mensaje (`{tipo, id}`), mostrado con su estado actual y con los permisos de quien lee, no de quien envía. Tarjeta propia: boleto, salida (el reporte de salida, calculado) y bus (con su croquis); el resto, genérica.
_Avoid_: adjunto, captura
Modelo: concepto
