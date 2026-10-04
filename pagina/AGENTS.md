# AGENTS.md — Página web pública

Venta de boletos en línea (ADR-021, ADR-023). Reemplaza a la aplicación Symfony aparte del legado (`docs/transportesfuentedelnorte.com`): una SPA que usa la API pública del backend y su base de datos, con las páginas públicas prerenderizadas en cada idioma para los buscadores.

## Cómo se sirve

- `npm run build`: type-check + `vite build` (cliente en `../backend/public/pagina/`) + `vite build --ssr src/entry-server.ts` (en `.ssr/`, se borra al final) + `node scripts/prerender.mjs`, que escribe `<idioma>/<ruta>/index.html` de las páginas públicas, `sitemap.xml` y `robots.txt`. `PAGINA_ORIGEN` (dominio de las canónicas, por defecto `https://transportesfuentedelnorte.com`); si `PAGINA_BACKEND` responde al compilar, la página de estaciones sale con el directorio incluido.
- El backend la sirve en `/pagina/` (`App\Controller\PaginaController`): `/pagina/` redirige al idioma del navegador; si hay HTML prerenderizado para la ruta lo entrega, si no el `index.html` de la SPA. Caddy no envía `/pagina*` al contenedor del frontend (por eso el dashboard del personal está en `/venta-en-linea`).
- La API es del mismo origen: `/api/publico/*` (sin sesión, limitada por IP) y el hub Mercure en `/.well-known/mercure`.
- `npm run dev`: Vite en `http://localhost:9100/pagina/` con proxy de `/api` y Mercure al backend (`PAGINA_BACKEND`, por defecto `http://localhost`, el stack local; `https://localhost` solo si el certificado del contenedor responde). El proxy no cambia el `Host`: la vuelta del banco (3-D Secure) queda en el mismo origen que la página, como en producción.
- `npm install` usa `legacy-peer-deps` (`.npmrc`), igual que el frontend.

## Stack

Vue 3 (`<script setup lang="ts">`), vue-router, Pinia, vue-i18n, PrimeVue 4 (tema Aura con preset de la marca, en la capa CSS `primevue`), FormKit 2, Tailwind 4 (tokens `marca-*` y `acento-*` en `main.css`), íconos de Tabler compilados con unplugin-icons (sin llamadas a Iconify en tiempo de ejecución). **Sin Stimulus ni Turbo.**

Reutiliza del frontend por alias (ver `vite.config.ts` y `tsconfig.app.json`): `@/core/croquis/*` y `@/shared/bus-map/*` (el mismo mapa del bus que ve la taquilla) y `@/shared/formkit/*` (inputs FormKit sobre PrimeVue). Esos archivos usan las APIs de Vue sin importarlas: por eso `unplugin-auto-import` (`src/auto-imports.d.ts` es generado). **`resolve.dedupe` y `paths`** fuerzan que Vue, PrimeVue, FormKit y Pinia se resuelvan desde `pagina/node_modules` aunque el import venga de `frontend/src`: con dos copias de PrimeVue, el tema solo queda registrado en una y los componentes de la otra salen sin variables (selects transparentes).

## Mapa

| Archivo | Qué hace |
|---|---|
| `src/app.ts`, `src/main.ts`, `src/entry-server.ts` | Arma la app (Pinia, router, i18n, PrimeVue, FormKit); cliente (monta sin hidratar); render de servidor para el prerender |
| `src/router.ts` | Rutas con idioma (`/:idioma/…`); `ESTATICAS` = páginas prerenderizadas e indexables |
| `src/seo.ts` | Título, descripción, canónica y `hreflang` (cliente y prerender) |
| `src/i18n/` | `index.ts` (idiomas, detección, `REGION` para Intl), `mensajes/*.ts` (interfaz; `es` es la fuente y el tipo de las demás), `contenido/*.ts` (historia, servicios, términos; `CONTACTO`) |
| `src/api.ts`, `src/tipos.ts` | Cliente y contratos de `/api/publico` |
| `src/viaje.ts` | Store de la compra: búsqueda, salidas por sentido, acordeón abierto, asientos elegidos (locales hasta pagar), `sessionStorage` |
| `src/carrito.ts` | Store del carrito del backend (token en `sessionStorage`: sobrevive a la vuelta del banco) |
| `src/modelo.ts` | Reglas puras (tarjeta, estado del mapa, elección, departamentos, fechas, importes) — con tests |
| `src/errores.ts` | Código del backend → mensaje traducido (+ motivo del banco) |
| `src/paginas/` | `InicioPagina` (toda la compra), `PagoPagina`, `CompraPagina`; informativas: `Servicios`, `Estaciones`, `Nosotros`, `Politicas`, `Contacto` |
| `src/componentes/compra/` | `BuscadorViaje`, `ListaSalidas` (acordeón), `TarjetaSalida`, `CroquisSalida` (mapa en vivo), `BarraCompra` (total + "Pagar asientos") |
| `src/componentes/` | Encabezado (menú en cajón en móvil), pie, selector de idioma, resumen del carrito, desafío del banco, íconos |
| `src/tresDs.ts` | Pasos del navegador en 3-D Secure (formularios a iframes, datos del navegador, huella) |
| `scripts/prerender.mjs` | HTML por idioma, `sitemap.xml`, `robots.txt` |

## Flujo (ADR-023)

1. Inicio: origen y destino (agrupados por departamento), ida y vuelta, fechas. Al cambiar cualquier campo se buscan las salidas de ida (y de regreso); la búsqueda queda en la URL (`?o=&d=&f=&r=`).
2. Cada salida (hora, llegada, empresa, bus, paradas, precios A/B, libres de total) se abre como acordeón con el croquis en vivo (Mercure). Tocar un asiento lo elige **en el navegador**; si otro lo ocupa, se quita y se avisa. La barra de abajo suma el total.
3. "Pagar asientos": `POST /api/publico/carritos` aparta ida y regreso juntos o nada; si otro tomó alguno (409), se quitan de la elección y se avisa sin pasar al pago.
4. Pago: resumen con tiempo restante, datos del comprador, tarjeta y dirección de facturación. 3-D Secure como antes (`tresDs.ts`, `DesafioBanco.vue`); tras cada paso se reenvía el pago con `continuar`. "Volver a los asientos" o salir sin pagar suelta los asientos.
5. Compra: se descarga el PDF (ida y regreso en el mismo archivo) una vez y queda el botón; el boleto también llega por correo.

Con `CYBERSOURCE_ORG_ID`, la página carga el script de huella del dispositivo del banco (`/api/publico/carritos/{token}/huella`).

Con la pasarela simulada: `4000 0000 0000 0002` rechaza, `4000 0000 0000 3220` pide 3-D Secure, otra Visa/Mastercard válida aprueba.

## Idiomas

`es` (fuente), `en`, `fr`, `de`, `it`. Para agregar un texto: la clave en `mensajes/es.ts` y en las demás (el test `idiomas.spec.ts` exige las mismas claves). En vue-i18n `{`, `}`, `@` y `|` son sintaxis: no usarlos literales en los mensajes. Fechas e importes con `Intl` según `REGION[idioma]`; la zona horaria siempre `America/Guatemala`.

## Convenciones

Las del frontend (`../frontend/AGENTS.md`): mobile-first, lógica en funciones puras con tests, textos en español de Guatemala (usted). No guardar ni registrar datos de tarjeta en el navegador. Lo que use `window`/`document`/`sessionStorage` debe tolerar el render de servidor (prerender): en `onMounted` o protegido.
