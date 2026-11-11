# ADR-057: El rol de sistema anula, reasigna y cancela boletos y encomiendas en cualquier estado

**Estado:** Aceptada (amplía ADR-028, ADR-033, ADR-035, ADR-037, ADR-038 y ADR-040 para el rol de sistema)

## Contexto

Anular y reasignar un boleto solo se puede con él emitido (o pospuesto) y antes de la hora de salida;
cancelarlo, con él emitido o chequeado y la salida programada, abordando o iniciada; anular una
encomienda, solo si no ha viajado (en la bodega de su origen). Pasada la hora, una autorización
fuera de hora (ADR-040) cubre anular y reasignar, pero no un boleto ya chequeado, en tránsito,
finalizado o cancelado, ni una salida finalizada.

Para corregir errores de operación (un boleto que se dio por finalizado y nunca viajó, una encomienda
entregada por error, un pasajero que se reubicó a mano) el administrador del sistema
(`ROLE_SUPER_ADMIN`, el rol con `sistema = true`, ADR-039) necesita operar sobre esos registros sin
tocar la base a mano y dejando la bitácora de siempre.

## Decisión

1. **La máquina admite los estados de origen del rol de sistema; el guard los cierra a los demás.**
   `TransicionBoleto` y `TransicionEncomienda` separan `desdeHabitual()` (las reglas de siempre) de
   `desdeSoloSistema()`; `desde()` es la unión y de ahí sale el workflow (ADR-037, ADR-038). Para
   quien no tiene el rol de sistema, el guard rechaza esos estados con el mismo mensaje, código y
   estado HTTP que antes daba la máquina (`ReglasTransicion::porEstado`): la API no cambia.

   | Transición | Habitual | Además, el rol de sistema |
   | --- | --- | --- |
   | boleto `anular` | emitido, pospuesto | chequeado, tránsito, finalizado, cancelado |
   | boleto `reasignar` | emitido, pospuesto | chequeado, tránsito, finalizado, cancelado |
   | boleto `cancelar` | emitido, chequeado | tránsito, finalizado, pospuesto |
   | encomienda `anular` | recibida, desembarcada (en su origen) | embarcada, tránsito, desembarcada en otra parada, entregada |
   | encomienda `anular_con_boleto` | recibida, embarcada, desembarcada | tránsito, entregada |

2. **Sin mirar la salida ni la hora.** Para el rol de sistema, `anular`, `reasignar` y `cancelar`
   (`TransicionBoleto::delSistema()`) no exigen el estado de la salida ni la hora, ni autorización
   fuera de hora. Sigue exigida la venta confirmada (una venta en proceso no se toca).
3. **Lo que ya no tiene valor no se opera, ni con el rol de sistema.** Un boleto anulado (su importe
   se devolvió y su factura se anuló) o reasignado (su importe pasó al boleto nuevo, en la misma
   venta) no se anula, reasigna ni cancela: descuadraría la factura (`ReglasBoletos::totalFacturable`)
   y la devolución a la agencia. Una encomienda anulada, tampoco.
4. **Lo demás sigue igual.** Al reasignar, la salida destino sigue sus reglas (misma empresa, mismo
   precio, vendible y antes de su hora). La encomienda con boleto se anula solo con su venta, ahora en
   cualquier estado si quien anula la venta es el rol de sistema. La estación de origen al anular una
   encomienda se sigue exigiendo a quien tiene estación. Las transiciones que causa la salida
   (`partir`, `llegar`, `no_presentarse`) y revertir no cambian.
5. **Quién es el rol de sistema** lo decide `Autorizacion::permisos()->sistema()` sobre el usuario de
   la petición (como al eliminar mensajes del chat): sin sesión (worker) no lo es, y suplantando a otro (ADR-054)
   se opera con los permisos del suplantado. Los guards (`GuardiaBoleto`, `GuardiaEncomienda`) solo lo
   consultan para las transiciones de la tabla. Lo que se calcula para la pantalla sale del mismo
   lugar: las acciones del listado de boletos y de encomiendas (`FlujoBoleto::acciones`,
   `FlujoEncomienda::acciones`), y `operable` de los diálogos de anular y reasignar
   (`ConsultaBoletos`) y del croquis (`DetalleAsiento`), con `ReglasBoletos::exigirOperablePorSistema`.
   El frontend no cambia.

## Consecuencias

- El administrador del sistema corrige boletos y encomiendas desde las pantallas de siempre, con la
  bitácora de la transición (quién, cuándo, motivo) y la anulación de la factura en el certificador.
- `EstadoBoletoAsiento::puedeTransicionarA()` y `EstadoEncomienda::puedeTransicionarA()` admiten esos
  pares (el setter de la entidad es la última barrera, no la regla): quién puede lo decide el guard.
- Un boleto en tránsito o finalizado que el rol de sistema cancela y luego se revierte vuelve a
  `emitido` (revertir sigue exigiendo la salida programada, abordando o iniciada).
- Una encomienda anulada a bordo conserva su salida como historia; deja de contar a bordo porque las
  consultas filtran por estado.
