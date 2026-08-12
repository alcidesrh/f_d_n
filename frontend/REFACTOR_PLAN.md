# REFACTOR_PLAN — Reorganización arquitectónica conservadora (frontend)

Prompt maestro: `agents/Promt-refactorizacion.md`. Objetivo: **localización predecible, cohesión por feature, sin cambios de comportamiento**.

## CURRENT ARCHITECTURE

- `src/components/` mezcla shared UI, layout, forms, feature (crud) y demos enrutados.
- `src/crud/` feature completa embebida a raíz; sus componentes en `components/crud/`; su página en `views/`.
- `src/views/` mezcla páginas de app (Dashboard, FormKitDemo) y de feature (AgnosticCrudPage).
- `src/stores/` mezcla stores globales (pinia/global/ui) con el store de ciclo de vida de ragf.
- `src/config/` contiene 4 archivos muertos (nora/aura/lara/material-theme.ts) y `assets/` 2 CSS huérfanos.
- Configs rotas: AutoImport `@/store/globals` (ruta inexistente), dirs `./src/utils` (no existe), Components solo `src/components`, uno include `src/components/**`.
- `frontend/AGENTS.md` y `docs/directory-structure.md` referencian `packages/` (ya movido).

## PROBLEMS FOUND

1. Cajones de sastre: `components/`, `views/`, `stores/`.
2. Feature CRUD partida en 3 sitios (crud/ + components/crud/ + views/).
3. Demo store de ragf sin consumidores (POSSIBLY_UNUSED — M2 en curso, se conserva).
4. Tipos inline de crud dispersos en services (se centralizan en `features/crud/types.ts`).

## TARGET ARCHITECTURE

```
src/
├── app/       shell: main.ts, App.vue, router/, formkit.config.ts, pages/{Dashboard, FormKitDemo}.vue
├── shared/    components/ (PageHead, ThemeEditor, AppIcon+icon-paths) · layout/ (AppHeader, SidebarLeft, SidebarRight)
│              forms/ (Fk*×15 + useFormKitInput) · stores/ (pinia, global, ui + __tests__) · config/ (theme, nav)
│              styles/ (assets CSS activos) · data/ (mock.ts)
├── features/  crud/ (views, components, composables, services, types, __tests__) ·
│              ragf/ (transport, stores/ragf.ts, __tests__) ·
│              graphql-orm/ (core, vue, types.ts, docs, views/{GraphQLOrmDemo, DemoPlugingGraphql})
├── composables/  transversal (use-orm, use-collection, use-item, use-entity-mutations, graphql-orm-keys)
└── types/        dominio (index.ts, entities/, formkit-inputs.d.ts, vue-router.d.ts)
```

Dirección de dependencias: `app → features → types`; `shared`/`composables` transversales, sin depender de features. Sin barrels nuevos; imports por ruta directa. Auto-registro solo para `shared/{components,layout,forms}`; componentes de feature con imports explícitos.

## MAPPING OLD → NEW

| Actual | Destino | Razón | Riesgo |
|---|---|---|---|
| views/AgnosticCrudPage.vue | features/crud/views/ | página de feature | bajo |
| components/crud/*.vue | features/crud/components/ | cohesión | bajo |
| crud/{entity-meta,filters,form-schema,list-entities,relation-display}.ts | features/crud/services/ | cohesión | medio |
| crud/use-relation-options.ts | features/crud/composables/ | clasificación | bajo |
| tipos inline de crud | features/crud/types.ts | centralización | medio |
| crud/__tests__/ | features/crud/__tests__/ | proximidad | bajo |
| ragf/* | features/ragf/* | feature | medio |
| stores/ragf.ts | features/ragf/stores/ragf.ts | feature (sin consumidores) | bajo |
| components/{GraphQLOrmDemo,DemoPlugingGraphql}.vue | features/graphql-orm/views/ | demos del feature | bajo |
| main.ts, App.vue, router/, formkit.config.ts | app/ | shell | medio |
| views/{Dashboard,FormKitDemo}.vue | app/pages/ | páginas de app | bajo |
| components/{common/PageHead,ThemeEditor,icons/*} | shared/components/ | transversal | bajo |
| components/layout/* | shared/layout/ | transversal | medio |
| components/formkit/* | shared/forms/ | transversal | medio |
| stores/{pinia,global,ui}.ts + __tests__ | shared/stores/ | transversal | medio |
| config/{theme,nav}.ts | shared/config/ | transversal | medio |
| assets/*.css (7 activos) | shared/styles/ | transversal | bajo |
| data/mock.ts | shared/data/ | transversal | bajo |
| config/{nora,aura,lara,material}-theme.ts, assets/{color,tailwind}.css | BORRAR | 0 referencias verificadas | bajo |

## MIGRATION BATCHES

1. Limpieza de muertos verificado (6 archivos). — HECHO
2. Feature crud: mover + extraer types.ts + imports + router. — HECHO (26 tests pass, type-check sin errores nuevos)
3. Feature ragf: mover + stores/ragf.ts.
4. Demos ORM → features/graphql-orm/views/.
5. Shell app: app/ + app/pages/ + index.html.
6. shared/: 6 subdirectorios + ~15 consumidores.
7. Configs: vite (AutoImport fix, dirs, Components), uno (include), regenerar d.ts, arreglar ui.spec.ts (parse error).
8. Docs: REFACTOR_PLAN, ARCHITECTURE, ARCHITECTURE_RULES, AGENTS.md + validación final.

## RISKS

- Regeneración de auto-imports.d.ts/components.d.ts (config antes de build).
- Auto-registro: PageHead/Fk*/AppIcon/AppHeader/Sidebars quedan en shared (sin impacto); AgnosticEntityList/Form pasan a imports explícitos en AgnosticCrudPage.vue.
- Router (6 rutas) y formkit.config.ts (15 imports Fk*).
- Baseline de errores pre-existentes: 58 en type-check, ui.spec roto, lint con deuda.

## VALIDATION STRATEGY

Por lote: mover → grep textual de referencias viejas → `npm run type-check` (sin errores NUEVOS vs baseline) → vitest acotado → reporte §41.
Final: `npm run type-check` · `npm run test:unit` · `npm run lint` · `npm run build`. E2E scaffold obsoleto registrado como deuda (no se toca).

## UNRESOLVED QUESTIONS

- Ninguna: decisiones tomadas (app/pages, imports explícitos de feature components, reparar ui.spec.ts, docs locales a frontend/).

## TECHNICAL_DEBT / POSSIBLY_UNUSED (no se arregla en esta tarea salvo lo pactado)

- theme.ts: código inalcanzable L486, console.log L384.
- stores/ui.ts: imports sin usar (updatePrimaryPalette, updateSurfacePalette, invertPalette, PRESET_OPTIONS, TAILWIND_COLORS, componentsPreset, var p).
- AgnosticCrudPage: dialogState `'edit'` vs FormMode `'update'` (error de tipo pre-existente).
- 58 errores de type-check pre-existentes (theme.ts TS2698/TS18048 etc.).
- Rutas con `name: "dashboard"` duplicado ×4; nav.ts con targets sin ruta (/flota, /rutas...).
- mock.ts: exports no consumidos (KPIS, BUSES, ...).
- stores/ragf.ts: sin consumidores (POSSIBLY_UNUSED; se conserva — RAGF M2 en curso).
- e2e/vue.spec.ts: scaffold obsoleto ("You did it!").
- ui.spec.ts: parse error por beforeEach comentado + cierre activo → **se repara en BATCH 7** (decisión tomada).