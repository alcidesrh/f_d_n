---

## Comandos

| Comando | Descripción |
|---|---|
| `npm run dev` | Dev server (vite, puerto 9000; en Docker se sirve detrás de Caddy en `https://localhost`) |
| `npm run build` | `type-check` + `vite build` (rolldown) |
| `npm run type-check` | `vue-tsc --build` |
| `npm run lint` | oxlint + eslint (ambos con `--fix`) |
| `npm run format` | oxfmt sobre `src/` (sin `;`, comillas simples) |
| `npm run test:unit` | Vitest |
| `npm run test:e2e` | Playwright contra el stack levantado (`make dev`); otra URL con `E2E_BASE_URL` |
| `npm run icons:meta` | Regenera `src/shared/icons/iconMeta.ts` (categorías/tags de Material Symbols); usa `bun` |

Nota: el contenedor usa `bun install && bun run dev`; el host usa npm.

---

## Stack

- **Runtime**: Vue 3.5 (`<script setup lang="ts">`), TypeScript ~6, vite 8 (rolldown)
- **UI**: PrimeVue 4.5 (auto-import vía resolver) + Tailwind CSS v4 + íconos Google Material Symbols (`<icon>`)
- **Forms**: FormKit 2 — inputs `Fk*` sobre componentes PrimeVue
- **Datos**: Apollo Client (GraphQL de API Platform) + `core/http` (REST)
- **Estado**: Pinia (con `pinia-plugin-persistedstate`)
- **Calidad**: oxlint + eslint + oxfmt + vitest (jsdom) + playwright

---

## Mapa del código (`src/`) — ver ADR-017

Dependencias en una sola dirección: `app → features → shared → core`. **Una feature no importa a otra**; lo compartido baja a `shared/` o `core/`.

| Carpeta | Qué hay | Empezar por |
|---|---|---|
| `main.ts` | Arranque: plugins → tema → schema GraphQL → montaje; sincroniza rutas con el backend | — |
| `app/` | Router y guards, tema (`ui.ts`, `theme.ts`), breakpoints (`breakpoints.ts`), sincronización de rutas (`routeSync.ts`), app instalable (PWA: `vite-plugin-pwa` en `vite.config.ts`, abre en `/chat` como una app de mensajería, íconos en `public/pwa/`; «Instalar app» del menú del usuario en `pwa.ts`, aviso de versión nueva en `layout/ActualizacionApp.vue`; el service worker solo precarga el código, nunca respuestas de la API) | `router.ts` |
| `app/layout/` | Shell: `AppShell` (header + sidebars; drawers debajo de `lg`), `BlankLayout`, toasts, barra de carga. Una ruta añade su panel derecho con `meta.panel` | `AppShell.vue` |
| `core/http.ts` | Único cliente REST (`Accept: application/ld+json`, Bearer, 401 → login, `silent` para polling) | — |
| `core/graphql/` | Cliente Apollo (`graphql`), documentos generados desde la metadata, introspección | `client.ts` |
| `core/auth/` | Sesión (`login`/`logout`), manejo de 401 | `session.ts` |
| `core/entities/` | Capa de datos de entidades: `schema` (metadata: `find`/`require`), `repository` (leer/escribir), `entityStore` (estado por entidad), `registry` (`getEntity(nombre)`) | `registry.ts` |
| `core/metadata/` | Configuración de presentación (`/api/entity_configurations`) | — |
| `core/navigation/` | Menús del usuario por área (`GET /me/menus`, `useUserMenusStore`); los pinta `app/layout/navigation/` (`NavArea`) | `userMenus.ts` |
| `core/venta/` | Venta de asientos (ADR-021): tipos, REST (`/venta/*`, `/agencias/*`), reglas puras (mapa, sube/baja, selección) y NIT | `modelo.ts` |
| `core/realtime.ts` | Suscripción a tópicos de Mercure (`suscribir(topico, cb, { token? })`; con `token`, tópicos privados) | — |
| `core/croquis/` | Croquis del bus (ADR-019): tipos, reglas puras (espejo de `App\Croquis\Croquis`) y REST (`/buses/{id}/croquis`, `/croquis/plantillas`) | `model.ts` |
| `core/notify.ts`, `core/loading.ts` | Toasts (`notify.success/error/…`) y contador de peticiones | — |
| `shared/ui/` | `Icon` (`<icon name="bus" lg />`), `PageHead`, `SortablePanelList` — registrados globalmente | — |
| `shared/icons/` | `IconPicker` y catálogo Material Symbols (carga diferida) | `iconCatalog.ts` |
| `shared/bus-map/` | Mapa del bus (`BusMap`, `SeatGlyph`, `SignalGlyph`, `BusMapLegend`): presentación pura para edición, ocupación y venta (`estado`, `interactivo`, slot `celda`). Excepción acotada a ADR-017: vocabulario del dominio, sin datos | `BusMap.vue` |
| `shared/boleto/` | Operaciones sobre boletos que usan varias features (ADR-028): `AnularBoletosDialog` (motivo, aviso de factura, incluir los boletos que faltan de la venta), `reimprimirBoletos` y el ticket de 80 mm (`ticket.ts`). Reglas puras en `core/venta/reasignacion.ts`, permisos en `core/venta/permisos.ts` | `AnularBoletosDialog.vue` |
| `shared/cliente/` | Buscar, registrar y editar clientes (NIT con consulta a la SAT): `ClienteBuscador` + `ClienteDialog`, con sus catálogos (`catalogos.ts`). Los usan la venta y las encomiendas | `ClienteBuscador.vue` |
| `shared/encomienda/` | Encomiendas en otras features (ADR-035): `ArticulosEditor` (paquetes por peso o especiales, con el importe cotizado) y la impresión de 80 mm (`impresion.ts`: recibo con contrato y términos, una guía por bulto) | `ArticulosEditor.vue`, `impresion.ts` |
| `shared/facturacion/` | `FacturacionFallidaDialog`: el certificador no emitió la factura (cancelar, reintentar, contingencia con permiso) | `FacturacionFallidaDialog.vue` |
| `shared/bitacora/` | `BitacoraDialog` (línea de tiempo de una salida o un boleto, `tipo` + `id`). Datos y formato en `core/bitacora/` (con tests) | `BitacoraDialog.vue` |
| `shared/barcode/` | Code 128 (`code128.ts`, espejo de `App\Venta\Boleto\Code128`) y `Barcode.vue` | — |
| `shared/formkit/` | Inputs `Fk*` y su registro en FormKit (`config.ts`) | `useFormKitInput.ts` |
| `shared/data-grid/` | `DataGrid` (ADR-029): grid de datos sobre CSS Grid, agnóstico de entidades (slots `cell`/`editor`/`filter`/`actions`). Geometría pura en `layout.ts`, resaltado en `highlight.ts` (con tests); reordenar (GSAP) y redimensionar en `useColumnDrag`/`useColumnResize` | `DataGrid.vue` |
| `features/entity-crud/` | Listado (`ListPage` sobre `DataGrid` + `list/`: filtros `useListFilters`, qué ofrece cada vista `listFeatures.ts`, selección `listSelection.ts`) y formulario (`FormPage` + `form/`) genéricos de cualquier entidad. Acciones por fila y sobre la selección por entidad (bitácora, anular, reasignar, reimprimir): `list/listActions.ts`; entidades de solo lectura: `entityReadOnly`. Formulario propio por entidad: `form/formOverrides.ts`; secciones extra del genérico, en pestañas junto a "Datos" (se guardan con él, contrato en `core/entities/formExtension.ts`): `form/formExtensions.ts`. Filtro a la medida del listado por entidad (panel propio, excluyente con los de columna, contrato en `core/entities/listCustomFilter.ts`): `list/listCustomFilters.ts` | `ListPage.vue`, `form/useEntityForm.ts` |
| `features/venta/` | Venta en taquilla y agencias (ADR-021): cliente, salidas del día, sube/baja, croquis en vivo (Mercure), cobro, fallo de factura, ticket de 80 mm (`shared/boleto/ticket.ts`) y modo reasignación (`store.iniciarReasignacion`, `/venta?reasignar=ids`: el cliente y el precio vienen de los boletos, se eligen salida y asientos, botón «Reasignar») | `VentaPage.vue`, `store.ts` |
| `features/ruta/` | Rutas con paradas (ADR-030): lista `/rutas` (búsqueda, ruta de regreso, duplicar, eliminar) y editor `/rutas/:id` (`nueva` crea): paradas reordenables con `SortablePanelList` en una, dos o tres líneas según el ancho, minutos por clase de bus, avisos y mapa Leaflet (`MapaRuta`). Datos y reglas puras en `core/ruta/` (con tests) | `RutasPage.vue`, `RutaEditorPage.vue` |
| `features/boleto/` | Listado de boletos (ADR-033), ruta `/boletos` (`?id=` abre uno): filtros del legado (siempre visibles, se aplican al cambiar; los de texto con retardo), panel de acciones sobre el boleto elegido (Consultar, Cancelar, Anular, Chequear, Reasignar, Correo, Imp. factura, Bitácora, Chat) y `DataGrid` paginado en el servidor con columnas ordenables/ocultables (recordadas por usuario). Datos y reglas puras (query, qué acción admite el boleto y por qué no) en `core/boleto/` (con tests) | `BoletosPage.vue` |
| `features/encomienda/` | Encomiendas (ADR-035): registrar `/encomiendas/registrar` (viaje, clientes, artículos con precio al instante, pagada/por cobrar/interna; imprime recibo y guías), buscar `/encomiendas` (filtros, atajos «En bodega aquí» y «Por entregar aquí», acciones Consultar, Embarcar, Desembarcar, Entregar con cobro, Anular, Recibo, Guías, Bitácora; `?id=` abre una) procesar por salida `/encomiendas/salida` (en bodega / a bordo, lector de código de barras, embarcar y desembarcar por lotes, manifiesto), entregar `/encomiendas/entregar` (entrega múltiple con escáner y cobro; imprime los comprobantes juntos), pendientes de envío `/encomiendas/pendientes-envio` (por destino, cambiar ruta) y especiales `/encomiendas/especiales` (catálogo). Datos y reglas puras en `core/encomienda/` (con tests) | `RegistrarEncomiendaPage.vue`, `EncomiendasPage.vue`, `ProcesarSalidaPage.vue`, `EntregarEncomiendasPage.vue` |
| `features/salida/` | Gestión de salidas (ADR-024): chequeo al abordar `/salidas/chequeo` (ADR-032: abordar, SI/NO por boleto, iniciar, finalizar; reglas puras en `core/abordaje/`), listado `/salidas` (próximas primero, filtros, editar/anular/eliminar con propagación y aviso de las omitidas) y programador `/salidas/programar` (ruta con paradas, ADR-030; cada hora con su distribución —`CroquisSelector`, galería de moldes— y un bus con ella, la hora de llegada según su clase y empresa como filtro, ADR-031; horas de 5 en 5 minutos; días, vista previa automática, esquemas guardados). Las rutas se eligen con `RutaSelect` (agrupadas por origen, búsqueda por palabras sueltas, `core/salida/rutas.ts`) y los buses muestran empresa y clase por separado (`BusOpcionItem`). Datos y reglas puras en `core/salida/` (API, filtro, programación, resultado, rutas; con tests) | `SalidasPage.vue`, `ProgramadorPage.vue` |
| `features/seguimiento/` | Mapa en tiempo real de buses en recorrido (ADR-025), ruta `/seguimiento`: Leaflet, filtros por empresa y salida, detalle del bus, animación con el reloj entre consultas (cada 30 s). Datos y reglas puras en `core/seguimiento/` (API, `posicionEn`, filtro; con tests) | `SeguimientoPage.vue`, `posicion.ts` |
| `features/chat/` | Chat interno (ADR-026), ruta `/chat/:canal?`: bandeja (búsqueda única de conversaciones y personas, grupos), conversación (días, rachas, scroll hacia atrás), redactor con fotos/archivos (clip, pegar, soltar; `imagen.ts` reduce las fotos antes de subir), registros con el selector (`SelectorRegistros`: recursos, listado genérico inyectado, carrito por recurso), lo que hay "en pantalla" y respuesta citada, visor de fotos, "visto" y tarjetas por tipo (`tarjetas/catalogo.ts`: sumar aquí un tipo con vista propia). Modos centro (la página), esquina y flotante (`ChatVentana`, la monta el shell; GSAP Draggable; geometría pura en `core/chat/ventana.ts`, estado persistido en `ventana.ts`). En el teléfono (< 768 px, `ventana.movil`) no hay ventanas: siempre la página a pantalla completa, como una app de mensajería (la barra con «Volver a la app» solo en la bandeja, la conversación entra deslizándose, volver a la bandeja es «atrás» del historial, el alto sigue al teclado con `altoVisible.ts`). Con el dedo (`gestosTactiles.ts`, reglas puras en `core/chat/gestos.ts`): deslizar un mensaje a la derecha lo responde, mantenerlo pulsado abre sus acciones en `HojaAcciones` (responder, copiar, ver el citado), con la app instalada deslizar desde el borde izquierdo vuelve a la bandeja (en el navegador ese gesto es suyo), y en el teléfono el clip abre la hoja de adjuntar (cámara, fotos y archivos, registros). `ChatAviso` (lo monta el shell) avisa de mensajes fuera de la conversación abierta. Con la app cerrada, Web Push: suscripción por navegador en `core/chat/push.ts` (interruptor en Mi cuenta, baja al cerrar sesión), lo muestra `public/push-sw.js` (importado por el service worker; se calla si la app está a la vista) y tocarla navega con `router.push` (`app/pwa.ts`). Enchufes con otras features en `shared/chat/integracion.ts` (`LISTADO_DE_REGISTROS`, `anunciarEnPantalla`). Datos, reglas puras (con tests) y store con Mercure en `core/chat/`; "Enviar por chat" en `shared/chat/EnviarPorChatDialog` | `ChatPage.vue`, `core/chat/store.ts` |
| `features/reporte/` | Reportes de venta, rutas `/reporte/cuadre-venta-boletos`, `/reporte/detalle-factura-boletos` y `/reporte/cuadre-voucher`, y de encomiendas `/reporte/encomienda-*` (una sola pantalla configurada por `REPORTES_ENCOMIENDA` en `core/reporte/encomienda.ts`, y la del manifiesto por salida): parámetros, vista previa con las cifras (`formato=resumen`) y generación de PDF/Excel. Datos y reglas puras en `core/reporte/` (API, tipos, filtros con tests) | `ReporteLayout.vue`, `CuadreVentaBoletosPage.vue`, `ReporteEncomiendaPage.vue` |
| `features/pagina-web/` | Dashboard de la página web (ADR-023), ruta `/venta-en-linea`: compras con filtro y calculadora (tabla o tarjetas según el ancho del contenido), configuración (recargo, venta activa, cierre) y mensajes de contacto. Datos en `core/pagina-web/` (API, tipos, filtro puro con tests) | `ComprasPanel.vue` |
| `features/agencia/` | Gestión de agencias (ADR-034): registrar depósito `/agencias/depositos/registrar` (`RegistrarDepositoForm`, también en diálogo), buscar depósitos `/agencias/depositos` (filtros, Registrar/Acreditar/Rechazar, `DataGrid`; `?id=` o `?agencia=&estado=`) y saldos `/agencias/saldos` (tarjetas, totales por moneda, movimientos). Reglas puras en `core/agencia/` (con tests). Y la sección "Saldo" del formulario genérico de Agencia | `DepositosPage.vue`, `AgenciaSaldoSection.vue` |
| `features/bus/` | Editor del croquis del bus (sección "Croquis" del formulario de `Bus`): pilas, arrastre por puntero, pincel, deshacer, plantillas | `editor.ts`, `CroquisEditor.vue` |
| `features/entity-config/` | Editor de columnas/campos por entidad | `EntityConfigPage.vue` |
| `features/form-builder/` | Constructor de formularios FormKit | `FormBuilderPage.vue` |
| `features/menu-builder/` | Constructor de menús (ADR-018): árbol con GSAP Draggable sobre un esquema con sangría, paleta de ítems, áreas del shell | `outline.ts`, `MenuBuilderPage.vue` |
| `features/migracion/` | Panel de migración desde el sistema legado | `MigracionPage.vue` |
| `features/dashboard/` | Tablero de gestión (portada, `/`): alertas que piden acción, indicadores contra el periodo anterior, tendencia por canal (SVG propio, sin librería de gráficas), operación de hoy, venta anticipada, rendimiento por ruta, mapa de demanda día × hora, puntos de venta, saldo de agencias y flota. Colores de las gráficas como variables en `DashboardPage` (paleta validada para daltonismo). Datos, formato y lecturas puras en `core/tablero/` (con tests). Sin `reporte.ventas`, accesos directos | `DashboardPage.vue` |
| `features/auth/` | Login (el fondo animado está aparte en `LoginBackground`) | — |

Los tests viven junto al código, en carpetas `__tests__/`.

---

## Convenciones

- Siempre `<script setup lang="ts">`.
- **Tema claro/oscuro** (`app/ui.ts` + `app/theme.ts`): el modo oscuro **invierte las rampas** (`surface-*`, `primary-*` y los colores de PrimeVue: 50 ↔ 950) y pone `dark` en `<html>`. Por eso `bg-surface-0`, `text-surface-700` o `var(--p-surface-100)` ya funcionan en ambos modos: no los combines con `dark:` (se invertiría dos veces) ni uses `white`/`#fff`/`black` para superficies. `dark:` (sigue a la clase `.dark`, no al SO) solo para colores fijos de Tailwind (`text-red-600 dark:text-red-400`). El modo inicial sale del SO; se cambia con el botón del header o en el personalizador.
- **Mobile-first** (ADR-020, `docs/frontend/responsive.md`): estilos base para móvil y `md:`/`lg:`/`xl:` (o `@container`) para mejorar; sin breakpoints nuevos ni `max-width`. Iconos clicables con `tap-target` y `aria-label`. Una pantalla no está terminada si solo funciona en escritorio.
- **Imports explícitos.** El auto-import solo cubre Vue, Vue Router y Pinia; el auto-registro de componentes solo `shared/ui` y `shared/icons`. Todo lo demás se importa (`import { notify } from '@/core/notify'`).
- **Stores por usuario** (`core/auth/alcanceSesion.ts`): toda store es del usuario con sesión. Lo persistido va en claves propias de cada usuario y, al entrar otro, las instancias se descartan (el próximo `useX()` las crea con lo de él). `global: true` solo para lo que es de todos (sesión, schema, carga); `permanente` para la que vive toda la app (el tema): se recarga en el sitio. Lo guardado a mano en localStorage, con `claveDeUsuario(clave)`.
- **Estado en stores, lógica en funciones puras.** Filtros, orden, serialización de formularios, etc. van en módulos sin estado (con tests); los componentes orquestan.
- **Notificaciones**: `notify.*` (`core/notify`). **REST**: `http.*` (`core/http`). **GraphQL**: `graphql` (`core/graphql/client`) o, para entidades, `getEntity(nombre)`.
- Los componentes de feature emiten eventos; la navegación la decide la página (p. ej. `EntityForm` emite `cancel`/`deleted`, `FormPage` navega).
- `src` también compila bajo `tsconfig.vitest.json` con `"lib": []`: el código debe ser lib-agnostic.

---

## Datos y API

- Las colecciones GraphQL paginadas son hulls `PageConnection` (`{ collection { id } paginationInfo { totalCount } }`); `core/graphql/client` ya las aplana.
- Los ids son IRIs (`/api/buses/5`); `itemIri(entity, id)` los arma desde un id numérico.
- La introspección se guarda en el navegador. Si cambia el schema de forma incompatible, subir `SCHEMA_VERSION` en `core/entities/schema.ts`.
- `label` es un campo derivado de solo lectura en todas las entidades: no se envía al crear/editar.

---

## Caveats

- **`.env`**: `VITE_GRAPHQL_ENDPOINT`, `VITE_REST_ENDPOINT`, `VITE_MERCURE_URL` (se leen en `core/config.ts`).
- **Caché root-owned**: `node_modules/.vite` y `node_modules/.tmp` pueden quedar propiedad de root (build del contenedor sobre el bind-mount): `EACCES` en `npm run dev` o `TS5033` en `vue-tsc`. Fix: `sudo rm -rf node_modules/.vite node_modules/.tmp`.
- `auto-imports.d.ts` y `components.d.ts` son generados: no editarlos a mano.
