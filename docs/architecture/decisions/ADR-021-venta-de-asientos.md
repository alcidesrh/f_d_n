# ADR-021: Venta de asientos por tres canales (taquilla, agencia, web) con factura electrónica

**Estado:** Aceptada

## Contexto

Los asientos de un recorrido se venden de tres maneras:

1. **Taquilla (estación):** un usuario de la empresa atiende al cliente, elige recorrido, tramo y asientos, cobra (efectivo o POS externo) y entrega un ticket impreso con los datos de la factura electrónica (DTE) que emite un certificador FEL de la SAT.
2. **Agencias:** entidades externas que venden por comisión con la misma interfaz. Cada venta descuenta su total de un saldo que nunca puede quedar negativo; sus ventas no llevan factura electrónica. En el legado una agencia era una `estacion` con `tipoEstacion_id = 4` (con `agencia_saldo`, `agencia_porciento_bonificacion`, …) y sus usuarios colgaban de esa estación.
3. **Página web:** el cliente elige salida y asiento en un croquis en vivo, paga con tarjeta (3-D Secure), recibe el boleto en PDF (descarga y correo). Mientras paga, el asiento aparece como "precompra" en las taquillas; un comando del legado limpiaba las precompras abandonadas y garantizaba que 30 minutos antes de salir no quedara ninguna. La página era una aplicación Symfony aparte con acceso directo a la base del legado.

El modelo nuevo tenía `BoletoVenta`, `BoletoAsiento`, `BoletoTarifa` (sin resolución de tarifa) y `Factura`, pero ninguna operación de venta. El orden de las paradas de un trayecto no se guarda: los subtrayectos son pares `(origen, destino)` colgados del trayecto (el migrador genera todos los pares hacia adelante y además cuelga el trayecto inverso).

## Decisión

### Dominio (`App\Venta`)

- **Itinerario** (`Itinerario`, `Itinerarios`): las paradas de un trayecto se ordenan topológicamente a partir de sus subtrayectos (origen primero, destino al final, descartando aristas que llegan al origen o salen del destino: así el trayecto inverso no entra). Da el **tramo** `[desde, hasta)` de cada trayecto vendible y la hora estimada en cada parada (duración del trayecto origen→parada, `HorasRecorrido`).
- **Ocupación por tramos** (`Ocupacion`, `Disponibilidad`): un asiento está ocupado para un tramo si algún boleto vivo (no anulado ni reasignado, incluidas ventas pendientes de factura) o alguna reserva web vigente se solapa. El mismo asiento se vende en tramos que no se solapan (A→B y B→C). Un boleto con un trayecto ajeno al itinerario ocupa todo el recorrido (ante la duda no se vende dos veces).
- **Tarifa por especificidad** (`EspecificidadTarifa`, `ResolutorTarifa`): cada `BoletoTarifa` fija algunos de empresa, trayecto, hora, clase y bus (los demás son comodín). Aplica la que coincide en todos los que fija y fija más; empate → la más reciente. Se lee por DBAL para no perder las tarifas sin empresa bajo el `TenantFilter`. En taquilla se puede **cobrar el trayecto completo** del recorrido aunque el pasajero viaje un subtrayecto.
- **Concurrencia:** toda venta/reserva bloquea la fila del recorrido (`PESSIMISTIC_WRITE`) mientras valida disponibilidad y aparta; el saldo de una agencia se mueve con la fila de la agencia bloqueada.

### Canales

- **Taquilla / agencia** (`RegistroVenta`, `POST /api/venta/ventas`):
  1. Transacción con el recorrido bloqueado: valida, cotiza, crea la venta (`pendiente` si lleva factura) con sus boletos y, en agencias, descuenta el saldo.
  2. **Fuera de la transacción** certifica la factura (el recorrido no queda bloqueado mientras responde el certificador).
  3. Certificada → `confirmada`. Si falla, la venta **se borra** (nada queda registrado) y la API responde 502 `{ codigo: "facturacion", recuperable, permiteSinFactura }`: el usuario cancela, reintenta, o (permiso `venta.sin_factura`, fallo de comunicación) reintenta **sin factura electrónica** — la venta se confirma con la factura `pendiente` (contingencia) y `app:venta:certificar-pendientes` la certifica después.
  - Idempotente por `token` (UUID que genera el cliente por intento): un doble envío devuelve la misma venta.
  - **Cortesía** (el "voucher" del legado; permiso `venta.cortesia`): total 0, sin factura. Las agencias no emiten cortesías ni ventas en contingencia.
  - Si el proceso muere entre los pasos 1 y 3, `app:venta:purgar` borra las ventas `pendientes` abandonadas.
- **Web** (`Reservas`, `CompraWeb`, `/api/publico/*`, sin sesión):
  - **Carrito = reservas** (`ReservaAsiento`, token UUID): apartar un asiento crea/extiende el carrito; un carrito es un recorrido + un trayecto, hasta 10 asientos. Vencimiento deslizante de 15 minutos, **nunca después del cierre de venta en línea (30 minutos antes de la salida)**. La disponibilidad ignora las reservas vencidas, así que liberar un asiento no depende de ningún proceso; `app:venta:purgar` solo borra filas (con `--cada=N` corre permanente, como el comando del legado).
  - **Pago** (`PasarelaPago`): el backend recibe los datos de la tarjeta y los pasa a la pasarela sin guardarlos ni registrarlos (`Tarjeta` enmascara `__debugInfo` y no se serializa). Resultado `aprobado`, `rechazado` (motivo del banco) o `autenticacion` (3-D Secure: el navegador envía los campos por POST al ACS del banco, que vuelve a `/api/publico/pagos/retorno` y de ahí a `/pagina/compra/{token}`). Cada intento queda en `PagoWeb` (marca y últimos 4 dígitos).
  - **El dinero manda:** cobrado el pago, la venta se registra (`canal = web`, sin usuario) aunque falle la factura (queda `pendiente` y se reintenta). Si entre el cobro y el registro el asiento se perdió, se **reembolsa**.
  - Boleto: PDF (`BoletoPdf`, dompdf + Twig) descargable con el token y enviado por correo (Messenger, asíncrono).
- **Tiempo real:** cada cambio de ocupación publica `/recorridos/{id}/ocupacion` en Mercure (sin datos de clientes); taquilla y página vuelven a pedir la ocupación. En taquilla los vendidos se distinguen por canal (estación, agencia, web) y las reservas web salen como `reservado`.

### Agencias

- `Agencia` es una entidad propia (no un `Enclave`): nombre, NIT, contacto, `empresa` opcional (null = vende recorridos de cualquier empresa), `saldo` en centavos, `moneda`, `porcentajeBonificacion`. `Usuario.agencia` define que un usuario vende como agencia.
- El saldo solo cambia por `SaldoAgencia` y cada cambio deja un `AgenciaMovimiento` inmutable (depósito, bonificación, venta, ajuste) con el saldo resultante: el saldo es la suma de sus movimientos. `Agencia.saldo` no es escribible por la API; `POST /api/agencias/{id}/depositos|ajustes` con permiso `agencia.acreditar`.
- Migración: las estaciones `tipoEstacion_id = 4` pasan a `agencia` (ya no a `enclave`), su saldo entra como un ajuste inicial y sus usuarios quedan vinculados.

### Facturación electrónica

- Puerto `CertificadorFel` (`certificar(SolicitudDte): DteCertificado`, `CertificacionFallida` con mensaje para el usuario y `recuperable`). `Factura` guarda el snapshot (emisor, receptor, número, serie, UUID, fecha de certificación, certificador, total, XML).
- **Certificador: Forcon** (`App\Venta\Facturacion\Forcon`, documentación en `docs/certificador_factura_electronica/`). `FEL_CERTIFICADOR=forcon` lo activa (`FelActivo`); por defecto `simulado` (NIT receptor `0` → rechazo; `FEL_SIMULADO_FALLA=1` → sin respuesta).
  - Emisión por `EmitirDteJson` (Basic Auth; Forcon firma con el certificado del emisor). Factura `FACT`, un ítem de servicio por boleto con IVA 12 % incluido y desglosado, frases de la empresa (`Empresa.frasesFel`, p. ej. `1-1,2-1`), afiliación IVA (`Empresa.afiliacionIva`) y establecimiento SAT (`Establecimiento`: por estación o el por defecto de la empresa).
  - Adenda: **59 = código interno** (`FDN-VENTA-{id}`; Forcon rechaza un código ya certificado con `ESW-025`, así un reintento nunca emite dos facturas), 137 = ruta, y por ítem 129 = asiento y 132 = pasajero.
  - Nombre comercial, correo y dirección del emisor salen del servicio "Datos Emisor" de Forcon (caché 12 h): la dirección de cada factura es la registrada en la SAT.
  - **Credenciales por empresa**: Forcon asocia cada usuario a un NIT emisor (`EAL-092`), como en el legado (`factura_emisor.user_forcon`). Se guardan en `CredencialFel` con la clave cifrada (sodium, llave derivada de `APP_SECRET`; si APP_SECRET cambia hay que recargarlas), sin exponerlas por la API. Se cargan con `app:fel:credencial <nit> <usuario> [--probar=1]` o desde el legado (migrador `fel`). `FEL_FORCON_USUARIO`/`FEL_FORCON_CLAVE` sirven de respaldo si hay una sola empresa. `FEL_FORCON_URL`: pruebas `https://pruebasfel.eforcon.com`; la de producción la entrega soporte.
  - Errores con código (`EVI-049`, `ESW-025`, …) = problema del documento: no recuperable, mensaje claro al usuario. Sin código, 5xx o sin respuesta = recuperable (reintentar / contingencia).
  - **Contingencia SAT:** vender "sin factura electrónica" asigna un **número de acceso** de 9 dígitos que se imprime en el ticket; al certificar después se envía con él y con la fecha de emisión original.
  - Antes de apartar/cobrar: la SAT no admite CF desde Q2,500.00 (`EVI-221`); la web además consulta el NIT del comprador (servicio "Consulta de NIT") antes de cobrar. En taquilla el alta de cliente consulta la razón social (`GET /api/venta/nit`).
  - Anulación (`AnularDteJson`) y consulta de CUI están documentadas por Forcon pero no se usan aún (la anulación de boletos no tiene operación de dominio).
- Lo mismo para la pasarela: `PasarelaSimulada` (tarjetas de prueba `4000 0000 0000 0002` rechazada, `4000 0000 0000 3220` con 3-D Secure) hasta integrar el banco adquirente.

### Modelo y API

- `BoletoVenta`: `canal`, `estado` (`pendiente`/`confirmada`), `estadoFacturacion` (`certificada`/`pendiente`/`no_aplica`), `estacion`, `agencia`, `cliente` (a quién se factura), `tipoPago`, `moneda`, `total`, `cortesia`, `enviarCorreo`, `referenciaPago`, `tokenPublico`; `usuario` pasa a opcional (web). **Solo lectura por la API** (REST y GraphQL): antes era escribible por el CRUD genérico, lo que permitía crear ventas sin validar disponibilidad ni saldo. Lo mismo `Factura`.
- `BoletoAsiento.observacion`. `Cliente.tipoDocumento`, `numeroDocumento`, `nacionalidad` (`Nacion`). `Usuario.estacion` (valor inicial de la venta).
- Catálogos nuevos migrados del legado conservando ids: `TipoPago`, `Moneda`, `TipoDocumento`; las nacionalidades del legado se cargan en `Nacion` (tabla `pais`).
- Endpoints: `/api/venta/*` (contexto, recorridos del día por estación, detalle con paradas y trayectos vendibles, ocupación, cotización, venta, comprobante y PDF, búsqueda/alta de clientes), `/api/agencias/{id}/saldo|depositos|ajustes`, `/api/publico/*` (limitada por IP con `symfony/rate-limiter`) y `/pagina/*` (SPA).
- Dependencias nuevas: `dompdf/dompdf` (PDF del boleto) y `symfony/rate-limiter` (API pública).

### Frontend y página

- `frontend/src/features/venta/` (taquilla y agencia sobre `BusMap` interactivo), `features/agencia/` (sección "Saldo" del formulario genérico de Agencia), `core/venta/` (API, tipos, reglas puras, NIT), `core/realtime.ts` (Mercure), `shared/barcode/` (Code 128, espejo de `App\Venta\Boleto\Code128`). `EstadoAsiento` suma `ocupado-web` y `ocupado-agencia`; `reservado` deja de ser elegible en un mapa interactivo.
- `pagina/`: aplicación aparte (Vue 3, PrimeVue 4, FormKit, Tailwind 4; sin Stimulus/Turbo) compilada en `backend/public/pagina` y servida por el backend bajo `/pagina` (`PaginaController` devuelve el `index.html` para las rutas del cliente; Caddy ya no envía `/pagina*` al contenedor del frontend). Usa la API pública del mismo origen y reutiliza del frontend, por alias, el mapa del bus y los inputs FormKit.

## Consecuencias

**Positivas:**

- Un solo motor de venta para los tres canales: mismas reglas de tramo, disponibilidad y tarifa; la web deja de escribir en la base del legado.
- Doble venta imposible por diseño (bloqueo del recorrido + ocupación por tramos) y reventa de subtramos libres.
- La factura no bloquea el recorrido, un fallo del certificador no deja ventas a medias, y la contingencia queda registrada para certificar después.
- El saldo de las agencias es auditable (libro de movimientos).
- Las precompras vencen solas: el cierre de 30 minutos antes de la salida se cumple aunque el comando de limpieza no corra.

**Negativas / pendientes:**

- La pasarela de pago sigue simulada (falta la documentación del banco adquirente). La copia de la página anterior (`docs/transportesfuentedelnorte.com`) quedó en el repositorio como gitlink sin contenido.
- Si el proceso muere después de que el certificador emitió el DTE y antes de guardarlo, el reintento recibe `ESW-025` (código interno repetido) y la venta no se duplica, pero hay que recuperar ese DTE a mano (Forcon tiene una consulta por código interno, `ESW-045`, no documentada en lo recibido).
- El orden de las paradas se deduce de los subtrayectos; un trayecto sin subtrayectos solo vende origen→destino. Las horas por parada son estimadas (duración de los trayectos).
- La página no entra en la imagen de producción del backend automáticamente (el contexto de build es `backend/`): hay que compilarla (`npm run build` en `pagina/`) antes de construir la imagen.
- Anulación y reasignación de boletos siguen sin operación de dominio.
