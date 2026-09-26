---

## Comandos

| Comando | Descripción |
|---|---|
| `npm run dev` | Dev server (vite, puerto 9000; en Docker se sirve detrás de Caddy en `http://localhost`) |
| `npm run build` | `type-check` + `vite build` (rolldown) |
| `npm run type-check` | `vue-tsc --build` |
| `npm run lint` | oxlint + eslint (ambos con `--fix`) |
| `npm run format` | oxfmt sobre `src/` (sin `;`, comillas simples) |
| `npm run test:unit` | Vitest |
| `npm run test:e2e` | Playwright contra el stack levantado (`make dev`); otra URL con `E2E_BASE_URL` |
| `npm run icons:meta` | Regenera `src/shared/icons/tablerMeta.ts` (categorías/tags de Tabler); usa `bun` |

Nota: el contenedor usa `bun install && bun run dev`; el host usa npm.

---

## Stack

- **Runtime**: Vue 3.5 (`<script setup lang="ts">`), TypeScript ~6, vite 8 (rolldown)
- **UI**: PrimeVue 4.5 (auto-import vía resolver) + Tailwind CSS v4 + íconos Tabler (`<icon>`)
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
| `app/` | Router y guards, tema (`ui.ts`, `theme.ts`), sincronización de rutas (`routeSync.ts`) | `router.ts` |
| `app/layout/` | Shell: `AppShell` (header + sidebars), `BlankLayout`, toasts, barra de carga. Una ruta añade su panel derecho con `meta.panel` | `AppShell.vue` |
| `core/http.ts` | Único cliente REST (`Accept: application/ld+json`, Bearer, 401 → login, `silent` para polling) | — |
| `core/graphql/` | Cliente Apollo (`graphql`), documentos generados desde la metadata, introspección | `client.ts` |
| `core/auth/` | Sesión (`login`/`logout`), manejo de 401 | `session.ts` |
| `core/entities/` | Capa de datos de entidades: `schema` (metadata: `find`/`require`), `repository` (leer/escribir), `entityStore` (estado por entidad), `registry` (`getEntity(nombre)`) | `registry.ts` |
| `core/metadata/` | Configuración de presentación (`/api/entity_configurations`) | — |
| `core/navigation/` | Menús del usuario por área (`GET /me/menus`, `useUserMenusStore`); los pinta `app/layout/navigation/` (`NavArea`) | `userMenus.ts` |
| `core/croquis/` | Croquis del bus (ADR-019): tipos, reglas puras (espejo de `App\Croquis\Croquis`) y REST (`/buses/{id}/croquis`, `/croquis/plantillas`) | `model.ts` |
| `core/notify.ts`, `core/loading.ts` | Toasts (`notify.success/error/…`) y contador de peticiones | — |
| `shared/ui/` | `Icon` (`<icon name="bus" lg />`), `PageHead`, `SortablePanelList` — registrados globalmente | — |
| `shared/icons/` | `IconPicker` y catálogo Tabler (carga diferida) | `tablerCatalog.ts` |
| `shared/bus-map/` | Mapa del bus (`BusMap`, `SeatGlyph`, `SignalGlyph`, `BusMapLegend`): presentación pura para edición, ocupación y venta (`estado`, `interactivo`, slot `celda`). Excepción acotada a ADR-017: vocabulario del dominio, sin datos | `BusMap.vue` |
| `shared/formkit/` | Inputs `Fk*` y su registro en FormKit (`config.ts`) | `useFormKitInput.ts` |
| `features/entity-crud/` | Listado (`ListPage` + `list/`) y formulario (`FormPage` + `form/`) genéricos de cualquier entidad. Formulario propio por entidad: `form/formOverrides.ts`; secciones extra del genérico, en pestañas junto a "Datos" (se guardan con él, contrato en `core/entities/formExtension.ts`): `form/formExtensions.ts` | `ListPage.vue`, `form/useEntityForm.ts` |
| `features/bus/` | Editor del croquis del bus (sección "Croquis" del formulario de `Bus`): pilas, arrastre por puntero, pincel, deshacer, plantillas | `editor.ts`, `CroquisEditor.vue` |
| `features/entity-config/` | Editor de columnas/campos por entidad | `EntityConfigPage.vue` |
| `features/form-builder/` | Constructor de formularios FormKit | `FormBuilderPage.vue` |
| `features/menu-builder/` | Constructor de menús (ADR-018): árbol con GSAP Draggable sobre un esquema con sangría, paleta de ítems, áreas del shell | `outline.ts`, `MenuBuilderPage.vue` |
| `features/migracion/` | Panel de migración desde el sistema legado | `MigracionPage.vue` |
| `features/auth/`, `features/dashboard/` | Login (el fondo animado está aparte en `LoginBackground`) y portada | — |

Los tests viven junto al código, en carpetas `__tests__/`.

---

## Convenciones

- Siempre `<script setup lang="ts">`.
- **Imports explícitos.** El auto-import solo cubre Vue, Vue Router y Pinia; el auto-registro de componentes solo `shared/ui` y `shared/icons`. Todo lo demás se importa (`import { notify } from '@/core/notify'`).
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
