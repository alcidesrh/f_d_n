# Stores Auto-Importadas

Las stores en `src/stores/autoimport/` son auto-importadas globalmente. No requieren importación manual.

## loadingStore (`loadingStore.ts`)

**Store**: `useLoadingStore` (id: `loading`)

Gestiona el estado de carga global con contador y prioridades:

- `globalCount`: contador total de operaciones en curso
- `ops`: Map de operaciones por key con contador y prioridad
- `loading`: getter → `globalCount > 0`
- `isOpLoading(key)`: getter → verifica si una operación específica está cargando
- `highestPriority`: getter → prioridad más alta entre operaciones activas
- `start(key, priority)`: inicia operación
- `stop(key)`: finaliza operación
- `reset()`: limpia todo

## menu (`menu.ts`)

**Store factory**: `useMenuStateStore(storeId, menu)`

Crea stores de menú dinámicamente. Cada instancia de menú tiene su propia store con estado persistido (`toggle` y `menu`).

## sidebar (`sidebar.ts`)

**Store factory**: `useSidebarStore(storeId, position)`

Crea stores de sidebar dinámicamente. Soporta modos `large`, `mini`, `onhover`, `close`.

**Estado**: `mode`, `position`, `w` (ancho), `t` (top offset)
**Getter**: `modeStates` — mapa de modos disponibles
**Acción**: `setMode(mode)` — cambia modo guardando el anterior

## profilerStore (`profilerStore.ts`)

**Store**: `useProfilerStore` (id: `profiler`)

Almacena el `X-Debug-Token` de Symfony para depuración.

- `debugToken`: string o null
- `setToken(token)`: actualiza el token

## schemaStore (`schemaStore.ts`)

**Store**: `useSchemaStore` (id: `schemaStore`)

Almacena metadatos de entidades obtenidos vía introspección GraphQL. (Ver `stores/schema.md`)

## session (`session.ts`)

**Store**: `useUserSessionStore` (id: `userSession`)

Maneja autenticación y permisos. (Ver `stores/session.md`)

## useApolloStore (`useApolloStore.ts`)

**Store**: `useApolloStore` (id: `apollo`)

Wrapper del cliente Apollo Client 4. (Ver `stores/session.md` para session store completa)
