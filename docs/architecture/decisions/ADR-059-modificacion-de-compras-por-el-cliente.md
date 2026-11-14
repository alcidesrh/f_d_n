# ADR-059: «Mis compras» — el cliente modifica en la página lo que compró en línea

**Estado:** Aceptada (amplía ADR-023 y ADR-041; usa la reasignación de ADR-021 y la refacturación de «Crear factura»)

## Contexto

Quien compraba en la página no podía cambiar nada después: cualquier cambio de hora, ruta o asiento
pasaba por una estación o por el personal (dashboard `/venta-en-linea`). La página no tiene usuarios
ni contraseñas, y no se quiere pedirle al cliente que cree una cuenta.

## Decisión

### Acceso sin usuario ni contraseña: la llave del cliente

`App\Venta\EnLinea\Modificacion\LlaveCliente` firma (HMAC con `kernel.secret`) el correo del comprador
y un vencimiento (180 días). No se guarda en la base. La página la envía en la cabecera
`X-Llave-Cliente`. Llega al cliente por tres caminos, de menos a más acción suya:

1. Al terminar de pagar, la respuesta trae la llave y la página la guarda en el navegador
   (`localStorage`, `pagina/src/llave.ts`): en ese navegador, «Mis compras» abre sin hacer nada.
2. El correo del boleto trae el enlace «Mis compras» con la llave (solo si el destinatario es el
   correo del comprador de la compra en línea).
3. En otro dispositivo: escribe su correo y recibe el enlace (`POST /api/publico/mis-compras/acceso`;
   responde igual haya o no compras con ese correo).

Cada consulta devuelve una llave renovada. `PagoWeb.correo` (normalizado, con índice) es por lo que se
buscan las compras.

### Historial (`MisCompras`)

Un viaje por venta (la ida y, si hubo, el regreso). Primero los próximos (el que sale antes, arriba) y
luego los pasados (el más reciente, arriba). Los pasados ofrecen la factura (PDF del boleto con los
datos de la factura electrónica y, si el certificador lo da, su PDF); los próximos, modificar. El
cliente no anula ni cancela: no hay ningún endpoint para eso.

### Modificación (`ModificacionCompras`)

Por cada asiento comprado: otra salida (hora), otro trayecto (ruta) y otro asiento (también de otra
clase); además, agregar asientos. No se quitan asientos. Solo a salidas de la misma empresa (la
factura y el comercio de la tarjeta son por empresa) y que estén a la venta en línea.

- **Importe** (`ReglasModificacion`, puro): por asiento, si el nuevo cuesta más se cobra la diferencia;
  si cuesta menos, el boleto conserva lo pagado y **no se devuelve** la diferencia. Los asientos
  agregados pagan su precio. La penalización se suma aparte.
- **Política** (`PoliticaModificacion`, de `ConfiguracionPagina`): veces que se puede cambiar un mismo
  asiento (`BoletoAsiento.modificaciones`, que la reasignación arrastra al boleto nuevo; 0 = cerrado),
  minutos mínimos antes de la salida (30 min y sale 8:30 → hasta las 8:00) y penalización
  (`Penalizacion`: monto fijo, una vez por modificación o por asiento cambiado; agregar asientos no se
  penaliza; 0 = sin penalización). Se configura en `/venta-en-linea` → Configuración.
- **Cobro**: si hay algo que pagar, con tarjeta y 3-D Secure por la misma `PasarelaPago` de la compra.
  `ModificacionCompra` guarda el intento (token, cambios, monto, penalización, estado de la pasarela);
  los asientos de destino quedan apartados (`ReservaAsiento`) mientras se paga. Si el cobro pasa y el
  cambio no se puede aplicar, se reembolsa. Sin costo, se aplica de una vez.
- **Aplicar**: en una transacción, cada cambio es una reasignación (el boleto anterior queda
  `reasignado`, ADR-021) y los asientos nuevos se suman a la misma venta. La penalización queda en
  `BoletoVenta.cargoModificacion`, entra en el total y va como línea propia en la factura.
- **Factura**: se compara `Facturador::huella` (lo que se envía al certificador: receptor, líneas,
  montos) antes y después. Si cambió, se anula la factura vigente y se certifica otra
  (`GestionCompraWeb::reemitirVenta`). El cliente también puede cambiar el NIT y el nombre de la
  factura de una compra (`PUT …/compras/{id}/facturacion`), que la reemite. Al terminar se envía de
  nuevo el boleto por correo.

### API (`MisComprasController`, `/api/publico/mis-compras`)

`POST /acceso`, `GET ""`, `GET /viajes/{id}/boleto.pdf`, `POST /viajes/{id}/cotizar`,
`POST /viajes/{id}/modificar` (402 `requiere_pago` si hay costo y no llega tarjeta; con 3-D Secure
devuelve el paso del navegador y un `token` para continuar), `GET /viajes/{id}/huella`,
`PUT /compras/{id}/facturacion`.

## Consecuencias

- Quien tenga acceso al correo del comprador (o a su navegador) puede modificar sus viajes, no
  anularlos ni obtener reembolsos. La llave no da acceso a datos de tarjeta.
- Rotar `kernel.secret` invalida las llaves: el cliente pide otro enlace.
- Si la anulación en el certificador falla, la factura anterior sigue vigente y el viaje queda con la
  factura pendiente: el personal la rehace con «Crear factura».
- Los reportes de caja y la calculadora del dashboard no separan el cargo por modificación: va dentro
  del total de la venta.
- Los términos y condiciones de la página dicen que la modificación puede tener penalización y que una
  modificación de monto menor no se reembolsa.
