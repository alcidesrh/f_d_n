# ADR-035: Encomiendas (paquetería en la bodega de los buses)

**Estado:** Aceptada

## Contexto

Las empresas aprovechan los recorridos para transportar paquetes en la bodega del bus. Se ofrece de dos maneras:

1. **Con boleto (acompañada):** el pasajero lleva algo que no puede llevar consigo. Se registra al vender el boleto, el precio se suma al de los asientos y sale **una sola factura** con todo. Se imprime el boleto con la encomienda y la guía que se pega al bulto.
2. **Sin boleto:** el cliente llega a una estación a enviar algo a otra. Se registran remitente, destinatario, artículos, origen, destino y ruta. Se paga al recibirla (factura) o la paga quien la recibe al retirarla (**por cobrar**, factura al entregar). Se imprimen el recibo y las guías.

El legado (`EncomiendaController`, 3700 líneas) tiene un registro por artículo con su código, su propia factura compartida entre los de una operación, una bitácora de estados, rutas encadenadas (transbordo), cinco tipos de tarifa y tres documentos sin cobro (cortesía, autorización interna, efectivo). Datos de 2026 (105 000 encomiendas): el 99,97 % son paquetes o especiales; efectivo (28) y cortesía (0) casi no se usan; la autorización interna sí (10 %); el valor declarado, nunca (2); la tarifa por volumen vale 0 desde 2014; el 4 % hace transbordo (2–3 rutas); el 22 % de las por cobrar se factura al entregar.

## Decisión

### Modelo (`App\Entity\Encomienda`, `EncomiendaArticulo`)

- **Una encomienda es un envío**: remitente → destinatario, de un enclave a otro, con uno o más **artículos** (`EncomiendaArticulo`: paquete por peso o especial del catálogo, descripción, cantidad, peso y medidas). Cada bulto lleva una guía (`Bulto k de N`) con el mismo código de barras (el id con 8 dígitos). En el legado cada artículo era una encomienda con su código; aquí viajan y se entregan juntos.
- **Modalidad** (`ModalidadEncomienda`): `pagada` (factura al recibir), `por_cobrar` (factura al entregar), `interna` (envío interno de la empresa, sin cobro ni factura, permiso `encomienda.interna`; reemplaza la "autorización interna" del legado sin PIN) y `con_boleto` (la cobra la venta del boleto). Sin efectivo, cortesía ni valor declarado.
- **Estado** (`EstadoEncomienda`): `recibida` → `embarcada` → `transito` → `desembarcada` → `entregada`; `anulada` desde recibida o desembarcada en su origen. `ubicacion` es el enclave donde está en bodega (recibida o desembarcada); `salida`, la salida en que va o fue la última vez.
- **Empresa**: la que la transporta y la factura (en el legado, la del calendario de facturación de la ruta). Solo se embarca en salidas de esa empresa.
- **Transbordo** sin rutas encadenadas: se desembarca en cualquier parada de la salida y desde ahí se embarca en otra que pase por esa estación. La ruta del registro es la prevista (informativa).

### Tarifas (`App\Encomienda\Cotizador`, puro y con tests)

Igual que el legado, con lo que se usa:

- **Paquete**: tarifa por peso (`TarifaEncomiendaPeso`, rango en libras, la más reciente ya vigente) + recargo por distancia (`TarifaEncomiendaDistancia`, par de enclaves en cualquier sentido, la más reciente vigente; sin recargo si no hay).
- **Especial**: el precio del tipo (`EncomiendaEspecial.precio`), sin recargo por distancia (como el legado: `tarifa_especial_por_distancia` apagado).
- Importe = cantidad × (base + recargo). **Descuento por bultos** del envío: 3–5 → 5 %, 6–9 → 7 %, 10 o más → 10 %, redondeado a quetzal entero (`UtilService::aplicarDescuento`). Comprobado contra encomiendas reales del legado.
- `app:encomienda:legado` copia del legado los tipos especiales, la escala de peso vigente (los rangos 0–10, 10.01–20… quedan encadenados: «más de 10 hasta 20») y los recargos por distancia, y la sigla de cada estación (`Enclave.alias`, que la guía imprime en grande).

### Registro y factura (`App\Encomienda\RegistroEncomienda`)

Como la venta (ADR-021): en una transacción valida, cotiza y registra; si es `pagada` certifica la factura **fuera de la transacción** (`Facturador::certificarEncomienda`) y, si falla, la borra y ofrece reintentar o, con `venta.sin_factura`, contingencia (número de acceso, `app:venta:certificar-pendientes` la certifica después). Idempotente por `token`.

Al entregar una `por_cobrar` se cobra y se certifica la factura al cliente que paga (por defecto, el destinatario); si el certificador falla, la entrega no se registra (o contingencia con permiso).

### Con boleto (`RegistroVenta`)

`POST /api/venta/ventas` acepta `encomiendas: [{ articulos, observacion }]`: la encomienda queda en la venta (`Encomienda.boletoVenta`), de la parada donde sube a la donde baja el cliente, **embarcada** en la salida del boleto, a nombre del cliente (remitente y destinatario). Su importe se suma al total de la venta y la factura del boleto lleva un renglón por encomienda. Anular todos los boletos vivos de la venta anula sus encomiendas (la factura ya cubre todo).

### Movimientos (`App\Encomienda\MovimientosEncomienda`, reglas puras en `ReglasEncomienda`)

- **Embarcar** (`encomienda.embarcar`): en bodega en la estación del usuario, en una salida programada, abordando o iniciada de la misma empresa cuya ruta pasa por ahí y sigue; si la salida ya inició queda en tránsito. Por lotes (escáner).
- **Iniciar la salida** (abordaje, ADR-032) pasa sus encomiendas embarcadas a tránsito.
- **Desembarcar** (`encomienda.embarcar`): en una parada de la ruta de su salida; queda en bodega ahí.
- **Entregar** (`encomienda.entregar`): desembarcada en su destino o, como el legado, en tránsito en una salida que pasa por su destino (se desembarca al entregarla, queda en la bitácora); en la estación del destino (u oficina, sin estación); quién la retira (por defecto el destinatario) y, si es por cobrar, el cobro. **Entrega múltiple** (`POST /api/encomiendas/entregar`): varias a la misma persona, cada por cobrar con su propia factura (la factura sigue siendo 1:1 con la encomienda); las que fallan vuelven en `fallidas` (con `recuperable` si falló el certificador, para reintentar o entregar en contingencia).
- **Cambiar ruta** (`encomienda.registrar`, `POST /api/encomiendas/{id}/ruta`): la que espera en bodega, a otra ruta que pase por donde está y luego por su destino; no cambia la empresa ni la factura (el legado también cambiaba la empresa; aquí la factura ya es de esa empresa). Bitácora `ruta_cambiada`.
- **Anular** (`encomienda.anular`): en bodega en su origen (nunca viajó). La pagada anula su factura en el certificador; la con boleto se anula con su venta.

### Bitácora

`TipoRegistro::ENCOMIENDA` con `OperacionEncomienda` (recibida, embarcada, tránsito, desembarcada, entregada, anulada, ruta cambiada). La escribe `RegistradorBitacora` con la estación, la salida o quien la retiró en `detalle`. `GET /api/bitacora/encomienda/{id}` (`encomienda.ver`).

### Pantallas

- `/encomiendas/registrar`: remitente, destinatario, origen (la estación del usuario), destino, ruta prevista (y las empresas que la operan esta semana), artículos con su precio al instante, modalidad y cobro; imprime recibo (con contrato y términos, como el legado) y una guía por bulto.
- `/encomiendas`: listado con filtros (código, cliente, estado, modalidad, origen, destino, ubicación, fechas) y acciones sobre la elegida: Consultar, Embarcar, Desembarcar, Entregar, Anular, Recibo, Guías, Bitácora.
- `/encomiendas/salida`: procesar por salida (el "Procesar por Salida" del legado): las que esperan en mi estación y puede llevar, las que van a bordo y bajan aquí; embarcar y desembarcar por lotes o por código escaneado.
- `/encomiendas/entregar`: la «Entrega múltiple» y «Pendientes de entrega» del legado en una sola pantalla: lo que se puede entregar en mi estación (desembarcado aquí o en tránsito hacia aquí), buscador y escáner que marca, sugerencia de las demás del mismo destinatario, y la entrega con su cobro; imprime en un solo trabajo los comprobantes de lo cobrado. El legado registraba ahí facturas de papel con fecha atrasada; con factura electrónica no aplica.
- `/encomiendas/pendientes-envio`: lo que espera en la bodega de mi estación, por destino y con los días que lleva; cambiar su ruta.
- `/encomiendas/especiales` (`encomienda.especiales`): el catálogo de especiales (el «Registrar Especial» del legado): crear, editar precio y si van por cobrar, desactivar. Sin las banderas de cortesía/interna/factura del legado (ver arriba).
- `/venta`: sección «Encomiendas» en la venta de boletos.

### Reportes (`App\Reporte\Encomienda`, REST `/api/reportes/encomiendas/*`)

Como los de venta: consulta (`ConsultaReportesEncomienda`) → cálculo puro con tests → PDF (dompdf, `templates/reporte/encomienda_*.html.twig`) o Excel (`LibroXlsx`) o `formato=resumen` para la vista previa. Quién entregó y quién anuló sale de la bitácora (la encomienda no lo guarda). Un usuario con estación/empresa solo ve la suya.

| Reporte | Parámetros | Permiso |
| --- | --- | --- |
| Manifiesto de carga | salida, destino | `encomienda.ver` o `reporte.encomiendas` |
| Pendientes de entregar | estación de entrega, empresa, solo pagadas/por cobrar | ídem |
| Detalle general (PDF/Excel) | rango, empresa, origen, destino, solo pagadas/por cobrar | ídem |
| Cuadre | día, estación, empresa | `reporte.encomiendas` |
| Detalle de factura (PDF/Excel) | rango de cobro, estación, empresa | ídem |
| Detalle de guías internas | rango, estación de emisión, empresa | ídem |

El cuadre cambia respecto al legado: por usuario, lo facturado al recibir, lo registrado por cobrar, **lo cobrado al entregar** (que el legado no separaba) y las guías internas; las enviadas por salida (el «por propietarios»); lo recibido ese día que no salió; las por cobrar entregadas; las anuladas. Sin las secciones de efectivo/valores (no existen aquí) ni «por cobrar no facturado» (siempre se factura). Pantallas en `features/reporte/` (`ReporteEncomiendaPage` configurada por `REPORTES_ENCOMIENDA` en `core/reporte/encomienda.ts`, y `ManifiestoEncomiendasPage`); el manifiesto también sale de «Manifiesto» en Procesar por salida.

### Migración del legado (`App\Migration\Salida\EncomiendasLegado`)

Como los boletos, las encomiendas llegan con su salida: al migrar una salida (`Migrador`, en la misma transacción y después de sus boletos) se migran las que viajaron en ella (`primera_salida_id` o algún evento de su bitácora en esa salida) con todo su árbol: empresa, origen y destino, ruta prevista, remitente, destinatario, a quién se facturó y quién la retiró, usuario y estación que la recibió, tipo de pago, moneda, el especial del catálogo (si falta, se crea), la factura certificada, el boleto con que viaja y los eventos de su bitácora. La lectura de la fila del legado es pura (`EncomiendaLegada`, con tests):

- **Id**: conserva el del legado, que es el número de su código de barras: las guías ya pegadas en los bultos siguen escaneando. La identidad no se reajusta (las del legado van por los 10 millones; las nuevas, desde 1). `legacy_id` guarda el mismo número.
- **Estado**: el de `ultima_bitacora_id` (el legado no lo guarda en la encomienda); la «cancelada» (7) es una anulación. Eventos del mismo segundo (desembarcar y entregar) se ordenan por el ciclo de vida. Bodega: donde se recibió o se desembarcó por última vez. Salida: la última en que viajó ya migrada (con transbordo, se corrige al migrar la siguiente).
- **Modalidad**: factura → pagada, por cobrar → por cobrar, cortesía y autorización interna → interna (total 0); con boleto solo si el boleto está migrado y la encomienda no tiene factura propia.
- **Artículo**: uno por encomienda (el legado tenía un registro por artículo): paquete (peso y medidas) o especial; el efectivo va como paquete «Efectivo …». El importe es el precio en quetzales del legado; no separa recargo ni descuento.
- **Factura**: la certificada del legado (al recibir o al cobrar), una copia por encomienda si cubrió varias (aquí es 1:1); anulada la encomienda, su factura también. Sin factura certificada queda `no_aplica`: nunca `pendiente`, el cron certificaría envíos viejos.
- **Bitácora**: cada evento del legado con su usuario, fecha, estación, salida y quién retiró; se reconoce por `detalle.legado`, así que repetir agrega solo los nuevos. De ahí salen quién entregó y quién anuló en los reportes.

Idempotente: una ya migrada se completa (estado, bodega, salida, entrega, la factura del cobro, eventos nuevos). Lo que no se puede migrar se cuenta en `omitido_encomienda_*`. Las que nunca se embarcaron no tienen salida y no llegan por esta vía. Las tarifas siguen viniendo de `app:encomienda:legado`.

## Consecuencias

- `ClienteBuscador`/`ClienteDialog` y `FacturacionFallidaDialog` pasan a `shared/` (los usan la venta y las encomiendas); `/api/venta/clientes*` y `/api/venta/nit` aceptan también a quien registra o entrega encomiendas.

- Las encomiendas del legado se migran con su salida; las que nunca se embarcaron (en bodega de origen o anuladas sin viajar) no se migran.
- Faltan del legado el reporte de tarifas de especiales, el de anuladas por usuario y el de cortesías (no hay cortesía de encomiendas).
- Anular el cobro de una por cobrar ya entregada no está implementado.
- Reasignar el boleto no mueve su encomienda a la nueva salida.
