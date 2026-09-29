# AGENTS.md — Página web pública

Venta de boletos en línea (ADR-021). Reemplaza a la aplicación Symfony aparte del legado: ahora es una SPA que usa la API pública del backend y su base de datos.

## Cómo se sirve

- `npm run build` compila en `../backend/public/pagina/`; el backend la sirve en `/pagina/` (`App\Controller\PaginaController` devuelve el `index.html` para las rutas del cliente; Caddy no envía `/pagina*` al contenedor del frontend).
- La API es del mismo origen: `/api/publico/*` (sin sesión, limitada por IP) y el hub Mercure en `/.well-known/mercure`.
- `npm run dev` levanta Vite en `http://localhost:9100/pagina/` con proxy de `/api` al backend (`PAGINA_BACKEND`, por defecto `https://localhost`).
- `npm install` usa `legacy-peer-deps` (`.npmrc`), igual que el frontend.

## Stack

Vue 3 (`<script setup lang="ts">`), vue-router, Pinia, PrimeVue 4 (tema Aura con preset propio, en la capa CSS `primevue`), FormKit 2, Tailwind 4, íconos de Tabler compilados con unplugin-icons (sin llamadas a Iconify en tiempo de ejecución). **Sin Stimulus ni Turbo.**

Reutiliza del frontend por alias (ver `vite.config.ts` y `tsconfig.app.json`): `@/core/croquis/*` y `@/shared/bus-map/*` (el mismo mapa del bus que ve la taquilla) y `@/shared/formkit/*` (inputs FormKit sobre PrimeVue). Esos archivos usan las APIs de Vue sin importarlas: por eso `unplugin-auto-import` (`src/auto-imports.d.ts` es generado). `resolve.dedupe: ['vue']` evita una segunda copia de Vue.

## Mapa

| Archivo | Qué hace |
|---|---|
| `src/api.ts`, `src/tipos.ts` | Cliente y contratos de `/api/publico` |
| `src/carrito.ts` | Store del carrito (token en `sessionStorage`: sobrevive a la vuelta del banco) |
| `src/modelo.ts` | Reglas puras (tarjeta, tiempo restante, estado del mapa, fechas) — con tests |
| `src/paginas/` | Inicio → Salidas → Asientos → Pago → Compra |
| `src/componentes/` | Buscador de viaje, resumen del carrito, íconos |

## Flujo

1. Buscar origen/destino/fecha → salidas con precio "desde" y asientos libres.
2. Croquis en vivo: tocar un asiento lo aparta (reserva de 15 min, nunca pasado el cierre de 30 min antes de salir).
3. Pago: datos del comprador (NIT o CF, documento opcional) y tarjeta. Si el banco pide 3-D Secure, el navegador envía un formulario POST al banco y vuelve a `/pagina/compra/{token}`.
4. Compra: se descarga el PDF una vez y queda el botón; el boleto también llega por correo.

Con la pasarela simulada: `4000 0000 0000 0002` rechaza, `4000 0000 0000 3220` pide 3-D Secure, otra Visa/Mastercard válida aprueba.

## Convenciones

Las del frontend (`../frontend/AGENTS.md`): mobile-first, lógica en funciones puras con tests, textos en español de Guatemala (usted). No guardar ni registrar datos de tarjeta en el navegador.
