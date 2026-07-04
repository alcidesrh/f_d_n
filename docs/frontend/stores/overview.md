# Stores (Pinia 3)

## Configuración

Pinia se configura en `src/stores/index.ts` usando `pinia-plugin-persistedstate`:

```typescript
export default defineStore(() => {
  const pinia = createPinia()
  pinia.use(piniaPluginPersistedstate)
  return pinia
})
```

## Estructura

```
src/stores/
├── autoimport/          # Stores globales auto-importadas
│   ├── loadingStore.ts  # Estado de carga global
│   ├── menu.ts          # Fábrica de stores de menú
│   ├── profilerStore.ts # Token del profiler Symfony
│   ├── schemaStore.ts   # Metadatos de entidades (introspección GraphQL)
│   ├── session.ts       # Autenticación y permisos
│   ├── sidebar.ts       # Fábrica de stores de sidebar
│   └── useApolloStore.ts # Cliente Apollo global
├── action/              # Store de acción (permisos)
├── localidad/           # Store de localidad
├── permiso/             # Store de permiso
├── role/                # Store de rol
├── user/                # Store de usuario
├── persist.ts           # Config de persistencia para stores dinámicas
├── storeFactory.ts      # Fábrica de stores dinámicas (531 líneas)
└── index.ts             # Setup de Pinia
```

## Auto-import

Las stores en `src/stores/autoimport/` son auto-importadas globalmente por `unplugin-auto-import`. Esto permite usar `useLoadingStore()`, `useUserSessionStore()`, `useSchemaStore()`, `useApolloStore()` sin importación explícita.

## Persistencia

`pinia-plugin-persistedstate` persiste automáticamente el estado en `localStorage`. Cada store define qué campos persistir:

```typescript
// session.ts
persist: { pick: ['user', 'token', 'permissions'] }

// schemaStore
persist: { pick: ['entities', 'types'] }
```

Las stores dinámicas usan `src/stores/persist.ts`:
```typescript
export default {
  pick: ['name', 'filters', 'visibleColumns', 'columns', 'orderField', 'orderType', 'pagination', 'formSchema', 'options', 'config'],
}
```

## Tipos de stores

### Setup stores (function syntax)
Usan `defineStore(id, () => { ... })`. Son stores modernas con composables internos.
- `session.ts`
- `useApolloStore.ts`
- `profilerStore.ts`
- `loadingStore.ts`

### Option stores (object syntax)
Usan `defineStore(id, { state, getters, actions })`.
- `schemaStore.ts`
- `menu.ts`
- `sidebar.ts`
- Stores dinámicas de `storeFactory.ts`

### Stores dinámicas
Creadas en tiempo de ejecución por `storeFactory.ts` usando `defineStore(name, { ... })()`. No están pre-definidas — se generan bajo demanda para cada entidad.
