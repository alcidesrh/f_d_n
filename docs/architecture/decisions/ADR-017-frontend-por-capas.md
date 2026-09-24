# ADR-017: Frontend por capas (core / shared / features) con imports explícitos

**Estado:** Aceptada — reemplaza a ADR-009 en lo que respecta al código del proyecto.

## Contexto

El frontend había crecido sin una frontera clara entre infraestructura, UI reutilizable y pantallas:

- `src/init.ts` exportaba `let` mutables (`apollo`, `apiGraphql`, `apiRest`, `session`, `ui`, `loadingStore`) que unplugin-auto-import inyectaba en cualquier archivo, junto con todo `src/stores`, `src/composables` y `src/utils`. Ningún módulo declaraba sus dependencias; en los tests esas variables quedaban `undefined` (40 tests rotos) y el orden de arranque dependía de convenciones implícitas.
- La lógica de "entidad" estaba repartida entre `stores/schemaRepository`, `stores/entities/factory`, `composables/useEntityRegistry` y `lib/apollo`. Convivían tres clientes REST y dos APIs de notificación.
- Componentes de 600–800 líneas mezclaban UI, estado y llamadas a la API.

## Decisión

1. **Capas** en `frontend/src/`:
   - `app/` — arranque, router, tema y shell (layouts, header, sidebars, toasts, barra de carga).
   - `core/` — infraestructura sin UI: `http` (único cliente REST), `graphql/` (cliente Apollo + documentos + introspección), `auth/`, `loading`, `notify`, `metadata/` y `entities/` (schema, repositorio, stores y registro de entidades).
   - `shared/` — UI reutilizable sin conocimiento del dominio: `ui/`, `icons/`, `formkit/`.
   - `features/<nombre>/` — una carpeta por funcionalidad (`entity-crud`, `entity-config`, `form-builder`, `migracion`, `auth`, `dashboard`), con sus páginas, componentes, stores y lógica pura.
2. **Dependencias en una sola dirección**: `app → features → shared → core`. Una feature no importa a otra; lo que dos features comparten baja a `shared/` o `core/`.
3. **Imports explícitos**. El auto-import queda limitado a las APIs de Vue, Vue Router y Pinia. El auto-registro de componentes queda limitado a `shared/ui` y `shared/icons` (`<icon>`, `<PageHead>`, `<IconPicker>`…). Todo lo demás se importa.
4. **Estado en stores, lógica en funciones puras**. Cálculos derivados (filtros, orden, serialización de formularios, borrador del Form Builder, textos de migración) viven en módulos sin estado y con tests; los componentes orquestan.

## Consecuencias

**Positivas:**

- Para ubicar algo basta la capa: API y datos en `core/`, piezas reutilizables en `shared/`, cada pantalla en `features/<nombre>/`.
- Cada archivo declara de qué depende; los tests mockean módulos concretos (`vi.mock('@/core/graphql/client')`) en lugar de globales.
- Suite de tests en verde, 0 errores de TypeScript y lint limpio al cerrar el refactor.

**Negativas / a vigilar:**

- Más líneas de `import` por archivo (a cambio de no tener dependencias ocultas).
- La regla "una feature no importa a otra" no la verifica una herramienta: se sostiene en revisión de código. Si empieza a romperse, añadir una regla de lint (p. ej. `import/no-restricted-paths`).
