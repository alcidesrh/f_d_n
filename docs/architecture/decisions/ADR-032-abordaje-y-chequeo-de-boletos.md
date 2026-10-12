# ADR-032: Abordaje de salidas y chequeo de boletos (estado cancelado)

**Estado:** Aceptada

## Contexto

En el legado (TerminalOmnibus) el abordaje se hace en "Chequear Boletos por Salida" (`/boleto/chequearBoleto2.html`): se elige una salida y cada boleto se marca **SI** (se presentó: queda chequeado) o **NO** (no se presentó: queda **Cancelado**), con accesos a los manifiestos interno y del piloto. La salida se pone antes en "abordando" y después se "inicia", y al iniciar los chequeados pasan a tránsito y los emitidos que subían en el origen quedan cancelados.

El sistema nuevo tenía los estados (`EstadoSalida` y `EstadoBoletoAsiento`, ADR-014) pero ninguna de esas operaciones: solo las salidas migradas llegaban a "abordando" o "iniciada". Además el "Cancelado" (6) del legado no existía y la migración lo convertía en `anulado`, que es otra cosa: anular invalida la factura en Forcon y saca el importe de los ingresos (ADR-028), mientras que en el legado el cancelado conserva la venta y su factura (el importe sí se cobró) y solo libera el asiento.

## Decisión

### Estado `cancelado` del boleto

- `EstadoBoletoAsiento::CANCELADO` = el pasajero **no se presentó a abordar**. Solo desde `emitido` (desde el listado de boletos también desde `chequeado`, ADR-033). Libera el asiento (se puede volver a vender ese tramo) pero **la venta, su factura y su importe siguen**: no es una anulación.
- Los estados que no ocupan el asiento están en un solo lugar, `EstadoBoletoAsiento::liberanAsiento()` (anulado, reasignado, cancelado), y la restricción `ex_boleto_asiento_tramo` los excluye a los tres. Lo usan la ocupación de la venta, el buscador de salidas, el detalle de asiento, los asientos comprometidos y los manifiestos (el cancelado no viaja).
- Para la factura (`ReglasBoletos::exigirVentaCompleta`, `DatosBoleto`) el cancelado sigue siendo un boleto de la venta: una venta con un no presentado no se anula por partes.
- `emitido → transito` también se permite: es el boleto chequeado cuando la salida ya inició (sube en una parada intermedia).
- Migración del legado: 6 → `cancelado`; el total de la venta sigue sumándolo. Las bases migradas antes se corrigen con `bin/console app:venta:cancelados-legado`.
- Un cancelado se **revierte** a `emitido` desde el listado de boletos si su asiento sigue libre (ADR-033).

### Abordar, chequear e iniciar (`App\Salida\Abordaje`)

Reglas puras en `ReglasAbordaje` (con tests), las del legado:

- **Selector de salidas**: desde 24 h atrás hasta 6 h adelante. Un usuario con estación ve las que salen de ella (programadas o abordando) y las iniciadas que pasan o llegan a ella; uno sin estación (oficina), todas las programadas, abordando o iniciadas.
- **Abordar** (`programada → abordando`) e **iniciar** (`abordando → iniciada`): desde la estación de origen de la ruta o un usuario sin estación. Abordar, como mucho 2 h antes de la hora de salida (en el legado no había límite, pero una salida abordando ya no se edita ni se anula).
- **Chequear**: con la salida abordando o iniciada, desde 2 h antes hasta 30 h después de la hora de salida, solo boletos `emitido` de una venta confirmada, y cada boleto lo chequea la estación donde sube el pasajero (o un usuario sin estación). SI → `chequeado` (o `transito` si la salida ya inició); NO → `cancelado`. Todo o nada.
- **Al iniciar**: chequeados → `transito`; emitidos que suben en el origen (`ordenSube = 0`) → `cancelado`; los que suben más adelante quedan emitidos y los chequea su parada.
- **Finalizar** (`iniciada → finalizada`): desde la estación de destino de la ruta o un usuario sin estación, sin límite de hora (como el legado). En el legado los boletos no cambiaban (no existía "finalizado"); aquí se cierran: `transito` → `finalizado`, `chequeado` (salidas migradas que no pasaron por "iniciar") → `transito` → `finalizado`, y los `emitido` que nadie chequeó → `cancelado` (motivo en la bitácora). Una salida finalizada ya no se modifica.
- Cada operación bloquea la salida (como la venta) y avisa el cambio de ocupación por Mercure. La bitácora (ADR-028) anota `chequeado`, `transito`, `finalizado` y `cancelado` (con el motivo: no se presentó, o inició o finalizó sin chequearse); los cambios de estado de la salida ya se anotaban.
- No se envía el correo de "actualización de salida" del legado.

API (`AbordajeController`, errores `{ error, codigo }`): `GET /api/abordaje/salidas` (selector y `puede`), `GET /api/abordaje/salidas/{id}` (boletos emitidos, chequeados y en tránsito, cada uno con `chequeable`), `POST …/{id}/abordar`, `POST …/{id}/chequeo` `{ si, no }`, `POST …/{id}/iniciar`, `POST …/{id}/finalizar`. Permisos `boleto.chequear`, `salida.abordar`, `salida.iniciar`, `salida.finalizar` (permiso "Abordaje de Salidas"); los manifiestos siguen con `salida.ver`.

### Pantalla

`/salidas/chequeo` (`features/salida/ChequeoPage.vue`, menú "Chequear boletos"; también desde cada fila de `/salidas`): selector de salida, Abordar / Iniciar salida / Finalizar salida, manifiestos y bitácora; la tabla del legado (Boleto, Chequeado SI/NO, Asiento, Documento, Sube, Baja, Cliente) en ancho y tarjetas con "Presente" / "No se presentó" en angosto; "Todos presentes"; una barra para guardar que pide confirmar si hay NO. Se refresca sola por Mercure si se vende o anula durante el abordaje. Reglas puras en `core/abordaje/chequeo.ts` (con tests).

## Consecuencias

- Lo vendido en un asiento cuyo pasajero no se presentó se puede revender en la misma salida mientras aborda.
- Los reportes dejan de contar como anulados los cancelados migrados (tras correr `app:venta:cancelados-legado`): su importe vuelve a los ingresos, como en el legado.
- Un cancelado se revierte solo mientras nadie haya vendido o apartado su asiento en un tramo que se solape (ADR-033).
