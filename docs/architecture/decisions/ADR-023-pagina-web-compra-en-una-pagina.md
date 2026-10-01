# ADR-023: Página web: compra en una sola página, ida y vuelta, recargo, idiomas y SEO

**Estado:** Aceptada (reemplaza las partes de la página web de [ADR-021](ADR-021-venta-de-asientos.md): carrito, cierre en línea y flujo de pantallas)

## Contexto

La primera versión de `pagina/` (ADR-021) era un flujo de cuatro pantallas (buscar → salidas → asientos → pago) en español, que apartaba cada asiento al tocarlo y solo vendía un viaje. El negocio pidió reemplazar del todo la página anterior (Symfony aparte, `docs/transportesfuentedelnorte.com`) con:

- Público mayoritariamente en el teléfono: mobile-first.
- Compra en una sola pantalla: origen/destino agrupados por departamento, ida y vuelta, fechas; al cambiar cualquier campo aparecen las salidas (con precio A/B, asientos totales y ocupados); cada salida se abre como acordeón con el croquis; precio acumulado y "Pagar asientos" siempre a la vista.
- Al pulsar "Pagar asientos" los asientos deben verse ocupados (precompra) para taquillas y otros clientes; si alguno ya fue tomado, no se pasa al pago y se avisa. Las precompras abandonadas se liberan solas; no se vende faltando menos de una hora para la salida.
- Páginas estáticas de la página anterior indexables por buscadores; español e inglés (más francés, alemán e italiano), por defecto el idioma del navegador y con cambio manual.
- Un dashboard: compras (por defecto las completadas más recientes) con filtro detallado y calculadora sobre lo filtrado, y el porciento extra sobre la tarifa (el `compra_porciento` del legado).

## Decisión

### Carrito: se aparta todo al pagar, todo o nada

- La elección de asientos es local (en el navegador) hasta "Pagar asientos". Ahí `POST /api/publico/carritos` `{ token?, viajes: [{ salida, trayecto, asientos }] }` aparta en una transacción la ida y, si hay, el regreso (`Reservas::reservar`, `SolicitudCarrito`). Bloquea las salidas en orden de id (sin interbloqueos con taquilla), reemplaza lo que el carrito tuviera y, si algún asiento está vendido o apartado por otro, no aparta ninguno: 409 `asientos_no_disponibles` con `viajes: [{ viaje, salida, asientos, numeros }]`.
- Mientras el cliente elige, el croquis escucha Mercure: si otro toma un asiento elegido, se quita de la elección y se avisa.
- "Volver a los asientos" (o salir del pago sin pagar) suelta los asientos (`DELETE /carritos/{token}`).
- Todas las reservas del carrito vencen juntas: 15 minutos (se extiende mientras el pago está en curso), nunca después de `ReglasVenta::LIBERACION_MINUTOS` (30) antes de la primera salida. `app:venta:purgar` borra las vencidas y ahora publica el cambio en Mercure: los croquis abiertos ven el asiento libre al instante.
- La venta en línea cierra `ConfiguracionPagina::cierreMinutos` antes de la salida (60 por defecto, mínimo 45 = 30 de liberación + 15 de reserva).

### Ida y vuelta: un cobro, una venta por viaje

- Un solo pago con tarjeta (`PagoWeb`), con el comercio de la empresa de la **ida**. Al cobrar se registran dos `BoletoVenta` (una por salida), cada una facturada por la empresa de su salida, como si se hubieran vendido en taquilla. Si el registro de cualquiera falla, no se registra ninguna y se reembolsa (como en ADR-021).
- `tokenPublico` sigue siendo único: la venta del regreso usa `BoletoVenta::tokenRegreso($token)` (UUID v5 del token del carrito). `PagoWeb` enlaza ambas (`boletoVenta`, `boletoVentaRegreso`) y guarda `viajes`.
- El PDF y el correo llevan ambos boletos (una página por viaje).
- Si las empresas de ida y regreso son distintas, el dinero entra al comercio de la ida y la liquidación entre empresas es contable.

### Recargo de la página

- `ConfiguracionPagina` (una fila): `recargoPorciento`, `ventaEnLinea`, `cierreMinutos`, quién y cuándo la cambió. Se edita en el dashboard (`PUT /api/pagina/configuracion`, permiso `pagina.administrar`).
- `ReglasVenta::cotizarEnLinea` = tarifa + recargo, redondeado al centavo por asiento (`Recargo`). El boleto registra el precio con recargo y la factura lo cobra tal cual. `PagoWeb::recargoPorciento` fija el recargo al empezar el pago: un cambio en el dashboard no altera un pago en curso.

### Departamentos

`Enclave.departamento` (texto) agrupa orígenes y destinos. La migración del legado lo trae de `estacion.departamento_id`; `app:enclave:departamentos` lo copia en bases ya migradas.

### Idiomas y buscadores

- vue-i18n con es, en, fr, de, it; el idioma va en la URL (`/pagina/es/…`). `/pagina/` redirige según `Accept-Language` (`PaginaController`); en el navegador, la elección manual queda en `localStorage`. Los textos largos (historia, servicios, términos) están en `src/i18n/contenido/*.ts`. Los errores del backend se traducen por `codigo`; el motivo del banco se muestra tal cual.
- Prerender: `npm run build` compila el cliente, un bundle de servidor (`src/entry-server.ts`) y `scripts/prerender.mjs` escribe `<idioma>/<ruta>/index.html` de las páginas públicas (inicio, servicios, estaciones, nosotros, políticas, contacto) con `<title>`, descripción, canónica, `hreflang` y el CSS del tema de PrimeVue, más `sitemap.xml` y `robots.txt`. `PaginaController` entrega ese HTML; el navegador monta la SPA desde cero (sin hidratar: el estado de la sesión cambia lo que se pinta). `PAGINA_ORIGEN` fija el dominio de las canónicas.

### Dashboard

En la app del personal (`frontend/src/features/pagina-web`, ruta `/venta-en-linea`; no puede empezar con `/pagina` porque Caddy manda eso al backend), con el permiso `pagina.administrar` (permiso "Administracion Pagina Web"): compras con filtro (fechas de compra y de salida, estado, empresa, origen/destino, ida y vuelta, factura, tarjeta, monto, texto), calculadora sobre todo lo filtrado (lo cobrado, asientos, promedios y recargo cuentan solo las completadas) y suma de las filas marcadas; configuración; mensajes del formulario de contacto (`MensajeContacto`, también enviados a `PAGINA_CONTACTO_CORREO`).

## Consecuencias

- Un cliente que solo mira no aparta asientos: la precompra dura lo que dura el pago, no lo que dura la navegación.
- La sincronización con las taquillas se mantiene: mientras paga, sus asientos salen como `reservado`; al vencer o soltarse, se liberan y Mercure avisa.
- El endpoint por asiento (`POST /carritos` con `asiento`, `DELETE /carritos/{token}/asientos/{id}`) desaparece; las rutas antiguas de la SPA (`/salidas`, `/salida/:id`) redirigen al inicio.
- Una compra de ida y vuelta entre dos empresas cobra con el comercio de una sola: requiere conciliación contable entre ellas.
- El ticket en PDF, el correo y los mensajes del backend siguen en español (documentos fiscales y textos de la SAT).
- `pagina/vite.config.ts` y `tsconfig.app.json` resuelven Vue, PrimeVue, FormKit y Pinia desde `pagina/node_modules` aunque el archivo venga de `frontend/src` (antes había dos copias de PrimeVue: selects sin tema, transparentes).
