# AGENTS.md — Frontend (RAGF)

Frontend del monorepo: SPA Vue 3 sobre Quasar-style layout (Quasar no se usa), con CRUD dinámico impulsado por GraphQL. Se ejecuta dentro del contenedor Docker `frontend` (bun + vite) o en host con `npm run dev`.

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
- **UI**: PrimeVue 4.5 (auto-import via resolver) + PrimeIcons + Tailwind CSS v4 (`@tailwindcss/vite`) + unocss (preset wind4)
- **Forms**: FormKit 2 — wrappers `Fk*` sobre componentes PrimeVue
- **Estado**: Pinia 4 (con `pinia-plugin-persistedstate`)
- **Router**: vue-router 5
- **Datos**: RAGF (capa propia de transporte GraphQL) + `@tanstack/vue-query` + `zod` + `graphql` 17
- **Calidad**: oxlint + eslint (`@vue/eslint-config-typescript`) + oxfmt + vitest (jsdom) + playwright

---

## Arquitectura

- **RAGF** (`src/ragf/`): capa de transporte GraphQL/REST propia (sin Apollo). M1 (transport) hecho; M2 (introspection → Schema AST → Metadata Registry) en curso.
  - `types.ts` / `config.ts`: contratos + `resolveConfig()`
  - `transport/`: `client.ts` (GraphQLClient/RestClient), `errors.ts` (RagfError/RagfTransportError/RagfGraphQLError)
  - `index.ts`: `createRagf()`; store de ciclo de vida en `src/stores/ragf.ts` (se bootstrapea en `src/main.ts`)
- **GraphQL-ORM dinámico** (`packages/graphql-orm-core/` + `packages/graphql-orm-vue/`): cliente ORM por introspección en runtime (fuentes Live/SDL-snapshot/JSON), registrado en `main.ts` con `createGraphQLOrm`. No son packages npm — se consumen por alias (`@graphql-orm/core`, `@graphql-orm/vue`) en `vite.config.ts`/`tsconfig.app.json`; la capa vue importa el core por ruta relativa. Ver `packages/readme.md` (características, testing, limitaciones) y `src/components/GraphQLOrmDemo.vue` (demo `/demo-graphql`). El snapshot SDL vive en `public/schema.graphql` (regenerar con `api:graphql:export`). Tests: `npx vitest run packages/graphql-orm-core/test` + smoke E2E `npx jiti smoke-test.ts`.
- **Auto-imports** (unplugin-auto-import + unplugin-vue-components): Vue/Router/Pinia y dirs `./src/stores`, `./src/composables`, `./src/utils` se importan solos. `auto-imports.d.ts` y `components.d.ts` son **generados** — no editar a mano.
- **Componentes PrimeVue/FormKit**: resueltos por resolver de auto-import; los componentes locales de `src/components/` también se registran automáticamente.
- **Vistas**: `src/views/` (Dashboard, FormKitDemo), demos en `src/components/` (DemoPlugingGraphql, GraphQLOrmDemo, ThemeEditor).

---

## Convenciones

- Siempre `<script setup lang="ts">`; componentes multi-palabra (regla `vue/multi-word-component-names` desactivada, pero seguirla).
- En stores dentro de `src/stores/` **no usar** el parámetro `state` en getters: unplugin-auto-import inyecta un símbolo `state` bogus desde `./stores/ui`. Usar `st`.
- `src` se compila también bajo `tsconfig.vitest.json` que define `"lib": []` → el código de `src/` debe ser lib-agnostic (ej.: `super(message)` de un solo argumento en `errors.ts`, no opciones ES2022).
- Errores RAGF: usar `RagfError`/`RagfTransportError`/`RagfGraphQLError` + `formatGraphQlErrors`, nunca `Error` a pelo.
- Las colecciones GraphQL del backend son hulls `PageConnection` (`buses { collection { id } paginationInfo { totalCount } }`), no arrays planos.
- REST requiere header `Accept: application/ld+json` (sin él → 406).
- Tests contra backend real: opt-in con `LIVE_BACKEND=1` (ver `src/ragf/__tests__/live.smoke.spec.ts`); el resto de tests son puros (vitest + jsdom + `@vue/test-utils`).

---

## Caja negra / caveats

- **`.env`**: `VITE_GRAPHQL_ENDPOINT=http://localhost/graphql`, `VITE_MERCURE_URL=http://localhost/.well-known/mercure`. No hay proxy en vite; CORS del backend permite `http://localhost:9000`.
- **Caché de deps root-owned**: `node_modules/.vite` puede quedar propiedad de root (build del contenedor como root sobre el bind-mount) → `EACCES unlink .../@formkit_vue.js` en `npm run dev` de host. Fix: `rm -rf node_modules/.vite` (+ `docker compose restart frontend`).
- **Contenedor `frontend`**: sin mapeo de puerto a host; dev server interno sin verificación por curl (usar `docker exec frontend node -e "fetch(...)"`). Puede reportar unhealthy (pre-existente).
- Datos de ejemplo: `src/data/mock.ts`; modelo de dominio en `src/entities.ts`; rutas/nav en `src/config/nav.ts` + `src/router/index.ts`; formularios en `src/formkit.config.ts` + wrappers `src/components/formkit/Fk*.vue` (usa `useFormKitInput.ts`).

---

## Regeneración de artefactos

- `auto-imports.d.ts` y `components.d.ts` se regeneran al arrancar vite/dev o al correr build. No commitear cambios manuales.
