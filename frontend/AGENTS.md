---

## Comandos

| Comando | Descripción |
|---|---|
| `npm run dev` | Dev server (vite, puerto 9000) |
| `npm run build` | `type-check` + `vite build` (rolldown) |
| `npm run type-check` | `vue-tsc --build` |
| `npm run lint` | oxlint + eslint (ambos con `--fix`) |
| `npm run format` | oxfmt sobre `src/` |
| `npm run test:unit` | Vitest |
| `npm run test:e2e` | Playwright (`e2e/`) |
| `npm run preview` | Preview del build |

Nota: el contenedor usa `bun install && bun run dev`; el host usa npm. Hay `pnpm-lock.yaml` y `package-lock.json` en el repo, pero Docker y el flujo real usan **npm**.

---

## Stack

- **Runtime**: Vue 3.5 (Composition API, `<script setup lang="ts">`), TypeScript ~6, vite 8 (rolldown)
- **UI**: PrimeVue 4.5 (auto-import via resolver) + PrimeIcons + Tailwind CSS v4 (`@tailwindcss/vite`) 
- **Forms**: FormKit 2 — wrappers `Fk*` sobre componentes PrimeVue
- **Estado**: Pinia 4 (con `pinia-plugin-persistedstate`)
- **Router**: vue-router 5
- **Calidad**: oxlint + eslint (`@vue/eslint-config-typescript`) + oxfmt + vitest (jsdom) + playwright

---

## Arquitectura

- **Auto-imports** (unplugin-auto-import + unplugin-vue-components): Vue/Router/Pinia y dirs `./src/stores`, `./src/composables`, `./src/utils` se importan solos. `auto-imports.d.ts` y `components.d.ts` son **generados** — no editar a mano.
- **Componentes PrimeVue/FormKit**: resueltos por resolver de auto-import; los componentes locales de `src/components/` también se registran automáticamente.
- **Vistas**: `src/views/` (Dashboard, FormKitDemo), demos en `src/components/` 

---

## Convenciones

- Siempre `<script setup lang="ts">`; componentes multi-palabra (regla `vue/multi-word-component-names` desactivada, pero seguirla).
- En stores dentro de `src/stores/` **no usar** el parámetro `state` en getters: unplugin-auto-import inyecta un símbolo `state` bogus desde `./stores/ui`. Usar `st`.
- `src` se compila también bajo `tsconfig.vitest.json` que define `"lib": []` → el código de `src/` debe ser lib-agnostic (ej.: `super(message)` de un solo argumento en `errors.ts`, no opciones ES2022).

- Las colecciones GraphQL del backend son hulls `PageConnection` (`buses { collection { id } paginationInfo { totalCount } }`), no arrays planos.
- REST requiere header `Accept: application/ld+json` (sin él → 406).


---

## Caja negra / caveats

- **`.env`**: `VITE_GRAPHQL_ENDPOINT=http://localhost/graphql`, `VITE_MERCURE_URL=http://localhost/.well-known/mercure`. No hay proxy en vite; CORS del backend permite `http://localhost:9000`.
- **Caché de deps root-owned**: `node_modules/.vite` puede quedar propiedad de root (build del contenedor como root sobre el bind-mount) → `EACCES unlink .../@formkit_vue.js` en `npm run dev` de host. Fix: `rm -rf node_modules/.vite` (+ `docker compose restart frontend`).
- **Contenedor `frontend`**: sin mapeo de puerto a host; dev server interno sin verificación por curl (usar `docker exec frontend node -e "fetch(...)"`). Puede reportar unhealthy (pre-existente).
- Datos de ejemplo: `src/data/mock.ts`; modelo de dominio en `src/entities.ts`; rutas/nav en `src/config/nav.ts` + `src/router/index.ts`; formularios en `src/formkit.config.ts` + wrappers `src/components/formkit/Fk*.vue` (usa `useFormKitInput.ts`).

---

## Regeneración de artefactos

- `auto-imports.d.ts` y `components.d.ts` se regeneran al arrancar vite/dev o al correr build. No commitear cambios manuales.
