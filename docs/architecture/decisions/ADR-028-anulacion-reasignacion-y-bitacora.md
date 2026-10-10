# ADR-028: Anulación y reasignación de boletos, y bitácora de boletos y salidas

**Estado:** Aceptada

## Contexto

`EstadoBoletoAsiento` ya tenía `anulado` y `reasignado` (ADR-014) y el legado tenía ambas operaciones, pero el sistema nuevo no ofrecía ninguna: un boleto vendido no se podía deshacer. Tampoco quedaba rastro de quién creó, anuló o reasignó un boleto, ni de quién cambió el bus o el estado de una salida.

## Decisión

### Anular (`App\Venta\Anulacion\AnulacionBoletos`)

- Solo **antes de la hora de salida** y con la salida `programada` o `abordando`, sobre boletos `emitido` (`App\Venta\ReglasBoletos`, pura y con tests).
- El boleto pasa a `anulado` y su asiento queda libre.
- **Una factura cubre toda la venta** y el certificador no la anula por partes: si la venta tiene factura certificada o por certificar, hay que anular todos sus boletos vivos juntos (el rechazo dice cuáles faltan y la pantalla ofrece sumarlos). Las ventas sin factura (agencias, cortesías) se anulan por boleto.
- Orden por venta: (1) se anula la factura en Forcon (`AnularDteJson`, `CertificadorFel::anular`) fuera de transacción; si falla no cambia nada; (2) `Factura.anuladaEn`/`motivoAnulacion` se guardan enseguida, así un reintento no vuelve a llamar al certificador; (3) en una transacción con las salidas bloqueadas se anulan los boletos, la venta queda `EstadoFacturacion::ANULADA` si ya no tiene boletos vivos y, en agencias, se devuelve el precio (`TipoMovimientoAgencia::ANULACION`).
- Una anulación de varias ventas es **por venta**: devuelve `{ anulados, fallidos }`; lo que ya se anuló no se revierte si otra venta falla.
- Pide un motivo (obligatorio: Forcon lo exige).
- La devolución del cobro con tarjeta de una compra web no está automatizada.

### Reasignar (`App\Venta\Reasignacion\ReasignacionBoletos`)

- Cambio de asiento, en la misma salida o en otra, también solo antes de la hora de salida (de la original y de la nueva).
- **Mismo precio**, comparado por boleto (cada boleto nuevo toma el asiento de su misma posición en la selección). Se cotiza como la venta original (`POST /api/venta/cotizacion` con `venta`: su cortesía y, en la web, su recargo).
- **No entre empresas distintas**, salvo lo vendido en la página web.
- Los boletos reasignados deben ser de una misma venta. No se cobra ni se factura: el boleto nuevo queda **en la misma venta** (y con su factura) y apunta al original (`BoletoAsiento.reasignadoDe`); el original pasa a `reasignado`. El original se libera antes de comprobar la disponibilidad, así se puede pasar a un asiento que hoy ocupa otro boleto de la selección.
- Todo en una transacción con las salidas bloqueadas: si el asiento está tomado o el precio no coincide, no cambia nada.
- Pantalla: `/venta` en modo reasignación (`store.iniciarReasignacion`; también `/venta?reasignar=ids`). Reutiliza la venta normal (croquis, tramo, "cobrar el trayecto completo" para igualar precios), con el botón "Reasignar" y, al terminar, el ticket nuevo.
- Para poder revender un asiento anulado, la restricción única `(asiento, trayecto, salida)` de `boleto_asiento` ahora es un **índice parcial** que solo cuenta los boletos vivos.

### Ticket

`GET /api/venta/ventas/{id}?boletos=1,2` imprime solo esos boletos (de un mismo viaje). Por defecto el comprobante lleva los boletos vivos de la venta; si no queda ninguno, todos (y el ticket dice «ANULADO»).

### Bitácora (`App\Bitacora`)

- Tabla `bitacora` (`App\Entity\Bitacora`): `entidad`, `registro_id`, `operacion`, usuario (clave foránea con `SET NULL` y su nombre guardado aparte), `fecha` y `detalle` JSON. **Solo se agrega**, y **no tiene clave foránea al registro**: sobrevive a que la salida se elimine. Es genérica: sumar otra entidad es sumar un valor a `TipoRegistro` y un enum de operaciones.
- Operaciones: boleto `creado`, `anulado`, `reasignado` (`OperacionBoleto`); salida `creada`, `abordando`, `iniciada`, `finalizada`, `cancelada`, `cambio_bus`, `eliminada` (`OperacionSalida`).
- La escribe `RegistradorBitacora`, un listener de Doctrine (`postPersist`, `preUpdate`, `onFlush`+`postRemove`) con DBAL **dentro de la misma transacción** del flush: si la operación se revierte, su bitácora también. Por ser un listener, queda anotado cualquier cambio sin importar por dónde llegue (venta, gestión de salidas, GraphQL, consola) y no hay que acordarse de llamarlo. Lo que solo sabe el servicio (el motivo de una anulación) se pasa con `RegistradorBitacora::anotar()` antes del flush.
- No anota lo migrado del legado (registro con `legacyId` y sin usuario en sesión). Una salida del legado que alguien cancela desde la aplicación sí queda anotada (hay usuario), sin la fila «creada».
- Lectura: `GET /api/bitacora/{salida|boleto}/{id}` (`salida.ver`; para boletos, quien puede ver la venta). Pantalla: `shared/bitacora/BitacoraDialog.vue`, desde `/salidas` y desde los listados genéricos de `Salida` y `BoletoAsiento`.

### Permisos

`boleto.anular` y `boleto.reasignar` (permiso «Anulacion y Reasignacion de Boletos»). `GET /api/venta/boletos/permisos` dice qué puede hacer el usuario (el administrador no tiene las acciones listadas en su sesión: lo decide el backend).

### Listado de boletos

`BoletoAsiento` se publica por GraphQL **solo lectura** y pasa a extender `Base` (con `label` y `importe`). En `/lista/boleto-asiento` el modo selección ofrece reimprimir ticket, reasignar y anular sobre los boletos marcados (`features/entity-crud/list/listActions.ts`, `entityBulkActions`) y cada fila tiene «Bitácora». En el croquis de venta (y en «Ver» una salida) el detalle de un asiento ocupado ofrece Reasignar y Anular.

## Consecuencias

- El estado `iniciada`/`abordando`/`finalizada` de una salida todavía no lo cambia ninguna pantalla; cuando lo haga, la bitácora lo anotará sola.
- Un boleto reasignado a otra salida deja la venta con boletos en dos salidas: el ticket se imprime por viaje.
- Falta decidir el reembolso de cobros con tarjeta (Cybersource) de las compras web anuladas.
