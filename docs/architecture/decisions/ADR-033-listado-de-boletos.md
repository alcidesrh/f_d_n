# ADR-033: Listado de boletos (el "Boletos" del legado) y cancelar desde el listado

**Estado:** Aceptada

## Contexto

En el legado, "Boletos" (`/boleto/listar`) es la pantalla donde se busca cualquier boleto: un acordeón de filtros (id, reasignado, salida, tipo de documento, rango de fechas, estado, número y clase de asiento, serie y número de factura, origen, destino, referencia externa, autorización de tarjeta, cliente del documento y cliente del boleto), la barra de acciones sobre el boleto elegido (Consultar, Cancelar, Anular, Autorización, Revertir, Chequear, Reasignar, Correo, Imp. Factura) y una tabla paginada en el servidor. Sin rango, toma 30 días antes y después de hoy, y no deja pasar de seis meses; un usuario de agencia solo ve lo que vendió su agencia.

En el sistema nuevo había piezas sueltas (anular, reasignar, reimprimir y bitácora desde el listado genérico de `BoletoAsiento`; chequear por salida, ADR-032), pero no esa búsqueda ni "Cancelar".

## Decisión

### Pantalla `/boletos` (`features/boleto/BoletosPage.vue`)

- Filtros arriba (se aplican con «Filtrar» o Enter; abiertos desde `lg`), los mismos del legado. El rango cuenta la fecha de la salida **o** la de la venta; un id exacto se busca en cualquier fecha (enlaces del chat).
- Panel de acciones sobre el boleto elegido (clic o Enter en la fila): Consultar, Cancelar, Revertir, Anular, Chequear, Reasignar, Correo, Imp. factura, Bitácora y Chat. Las que exigen un permiso que el usuario no tiene no se muestran; las demás se deshabilitan con el motivo (`core/boleto/listado.ts::disponibilidad`, con tests). El backend vuelve a validar todo.
- Tabla sobre `DataGrid` (ADR-029) con las columnas del legado, ordenables donde el backend lo permite, redimensionables y ocultables (se recuerdan en el navegador); tarjetas en angosto.
- Cada acción reutiliza lo que ya existía: Anular → `AnularBoletosDialog` (ADR-028), Reasignar → `/venta?reasignar=id`, Chequear → `POST /api/abordaje/salidas/{id}/chequeo { si: [id] }` (ADR-032), Imp. factura → reimprimir el ticket de 80 mm (lleva los datos de la factura), Bitácora y Chat como en `/salidas`. La tarjeta de boleto del chat abre `/boletos?id=`.

### API (`BoletoController`, `/api/boletos`, permiso `boleto.ver`)

- `GET /opciones` (rango por defecto, tamaños de página y `puede`), `GET ?…` (página), `GET /clientes?q=` (para los filtros), `GET /{id}` ("Consultar": venta, factura, demás boletos de la venta y reasignaciones), `POST /{id}/cancelar { motivo? }` y `POST /{id}/revertir` (`boleto.cancelar`), `POST /{id}/correo { correo }`.
- `App\Venta\Boleto\Listado\FiltroBoletos` (lectura validada, con tests) y `ListadoBoletos`. El tipo de documento sale de la venta: voucher, cortesía, factura (certificada), factura pendiente, agencia o sin factura.
- **Dos pasos**: primero los ids de la página con solo las uniones que piden los filtros y el orden, luego las filas (el boleto con su viaje en una consulta y lo demás de la venta por lotes). Con todas las uniones en una sola consulta (17 tablas) PostgreSQL tardaba ~2,5 s en *planificarla* aunque la ejecución era de milisegundos.
- Correo: el mensaje `EnviarBoletoPorCorreo` acepta un destinatario; manda el PDF de la venta (todos sus boletos), no solo el elegido.

### Cancelar desde el listado

- Es el "Cancelar" del legado: el pasajero no viaja. Mismo efecto que el NO del chequeo (ADR-032): `cancelado`, libera el asiento, conserva venta, factura e importe, anota el motivo en la bitácora y avisa la ocupación por Mercure.
- Reglas (`ReglasAbordaje::exigirCancelable`): boleto **emitido o chequeado** (como el legado) y salida programada, abordando o iniciada; venta confirmada; un usuario de agencia solo cancela lo de su agencia. No depende de la estación ni de la hora (es una acción de supervisión, permiso `boleto.cancelar` en "Anulacion y Reasignacion de Boletos").
- Por eso `EstadoBoletoAsiento` admite ahora `chequeado → cancelado` (antes solo desde `emitido`).

### Revertir un cancelado

- Es el "Revertir" del legado: el pasajero sí viaja. `cancelado → emitido` (luego se chequea como cualquier otro); la bitácora anota `revertido` ("Cancelación revertida").
- Reglas (`ReglasAbordaje::exigirRevertible`, como el legado): boleto cancelado, salida programada, abordando o iniciada; ni cortesías ni boletos de agencia. Mismo permiso que cancelar (`boleto.cancelar`; en el legado, los mismos roles).
- Vuelve a ocupar el asiento, así que `Abordaje::revertir`, con la salida bloqueada, comprueba con la ocupación de la venta (`Disponibilidad` + `Ocupacion`) que nadie lo haya vendido o apartado en la página en un tramo que se solape; si no, `409 asiento_ocupado`. Avisa la ocupación por Mercure.

## No incluido

- **Autorización** (solicitudes de autorización de operaciones, `AutorizacionOperacion` del legado): es otro módulo, sin modelo nuevo todavía.
- El correo del legado iba al usuario y a su estación; aquí va a la dirección que se escribe (por defecto, la del pasajero o del cliente).

## Consecuencias

- El ítem "Buscar" del menú de boletos (que apuntaba a la portada) lleva a `/boletos` (migración `Version20261011220000`).
- El listado genérico de `BoletoAsiento` (`/lista/boleto-asiento`) sigue existiendo para selección múltiple.
