# Composables

Los composables encapsulan lógica reutilizable usando Composition API. Están en `src/composables/` y son auto-importados globalmente por `unplugin-auto-import`.

## entityRegistry (`src/composables/entityRegistry.ts`)

**Propósito**: Registry de stores dinámicas. Punto de entrada único para obtener stores de entidades.

```typescript
export async function getStore(entity?: string, refresh?: boolean): StateStore | false
```

- Si no se pasa `entity`, usa `useRoute().params.entity`
- Mantiene un `Map<string, Store>` interno
- Espera a que `schemaStore.isLoaded` sea true si es necesario
- Retorna `false` si la entidad no existe

## mercureItem (`src/composables/mercureItem.ts`)

**Propósito**: Suscripción Mercure para un item individual. Se conecta automáticamente cuando `store.hubUrl` está disponible.

```typescript
export function useMercureItem({
  store,           // Store del item
  deleteStore,     // Store para manejar eliminación
  redirectRouteName, // Ruta de redirección post-eliminación
})
```

- Detecta eliminaciones (payload de 1 campo) y redirige
- Actualiza item vía `store.setRetrieved(data)`
- Se limpia en `onBeforeUnmount`

## mercureList (`src/composables/mercureList.ts`)

**Propósito**: Suscripción Mercure para listas. Se conecta cuando `store.items` tiene datos.

```typescript
export function useMercureList({
  store,        // Store de la lista
  deleteStore,  // Store para manejar eliminación
})
```

- Actualiza items vía `store.updateItem(data)`
- Elimina items vía `store.deleteItem(data)`
- Se limpia en `onBeforeUnmount`

## useApiRest (`src/composables/useApiRest.ts`)

**Propósito**: Cliente HTTP REST construido sobre `fetch`. Expone dos funciones:

```typescript
export function createApi(options: ApiOptions): Api
export function useApi(): Api  // Obtiene la instancia global
```

**ApiOptions**:
```typescript
{
  baseURL: string
  getAccessToken?: () => string | null
  refreshToken?: () => Promise<string | null>
  onStart?: (key?) => void
  onEnd?: (key?) => void
}
```

**Api retorna**: `{ request, get, post, put, patch, delete, invalidate, cancelAll }`

Características: cache con TTL, deduplicación, reintentos, cancelación, y captura de `X-Debug-Token`.

## useBreadcrumbs (`src/composables/breadcrumb.ts`)

**Propósito**: Genera breadcrumbs desde la ruta activa.

```typescript
export function useBreadcrumbs(): { breadcrumbs: ComputedRef<BreadcrumbItem[]> }
```

Recorre `route.matched` y evalúa `meta.breadcrumb` que puede ser:
- **string**: breadcrumb estático
- **function(route)**: breadcrumb dinámico (retorna string o array)
- **array**: múltiples breadcrumbs con `{ label, to, icon }`

## usePermission (`src/composables/usePermission.ts`)

**Propósito**: Verificación de permisos planos.

```typescript
export function usePermission(): {
  can(code: string): boolean
  canAny(codes: string[]): boolean
  canAll(codes: string[]): boolean
}
```

Delega en `useUserSessionStore().can()` que verifica si el permiso está en el array de permisos del usuario.

## useEntityConfig (`src/composables/useEntityConfig.ts`)

**Propósito**: CRUD de configuración de entidades vía GraphQL. 152 líneas con queries y mutations.

```typescript
export const useEntityConfig = () => {
  fetchAllConfigs(): Promise<any[]>
  fetchConfig(entityClass: string): Promise<EntityConfig>
  fetchConfigAdmin(entityClass: string): Promise<EntityConfig>
  saveConfig(input): Promise<...>
}
```

- `fetchConfig` usa `noLoading: true` para no triggerear la barra de carga
- `fetchConfigAdmin` retorna una copia clonada con `useCloned`
- `saveConfig` usa `keepId: true` para conservar IDs en relaciones

## useIcons (`src/composables/useIcons.ts`)

**Propósito**: Carga y búsqueda de iconos Material Symbols desde GraphQL. 144 líneas.

```typescript
export function useIcons(): {
  icons: Ref<IconItem[]>
  categories: Ref<CategoryItem[]>
  loadIcons(): Promise<IconItem[]>
  loadCategories(): Promise<CategoryItem[]>
  loadAll(): Promise<void>
  searchIcons(term: string, category?: string): IconItem[]
  iconCategories(iconId: string): string[]
  findByCodigo(codigo: string): IconItem | undefined
  findById(id: string | number): IconItem | undefined
}
```

Mantiene caché interna con `iconsCache`, `categoriesCache` y `iconCategoryMap`. Usa queries GraphQL para `icons` e `iconCategories`.

## useBreakpoint (`src/composables/useBreakpoints.ts`)

**Propósito**: Acceso al servicio responsive via inject.

```typescript
export function useBreakpoint() {
  const responsive = inject('responsive')
  return responsive
}
```

Retorna el estado reactivo del `ResponsiveService`: `{ width, height, name, isMobile, isTablet, isDesktop }`.

## useNotifications (`src/composables/notifications.ts`)

**Propósito**: Notificaciones Quasar simplificadas.

```typescript
export function useNotifications(): {
  displayErrorNotification(message: string): void
  displaySuccessNotification(message: string): void
}
```

## useRouter / useRoute (`src/composables/useRouter.ts`)

**Propósito**: Acceso al router exportado desde `src/router/index.ts`.

```typescript
export function useRouter(): Router
export function useRoute(): Route
```

## useGsap (`src/composables/useGsap.ts`)

**Propósito**: Inicialización de GSAP con todos los plugins necesarios.

```typescript
export function useGsap(): { greensock: GSAP }
```

Registra: Draggable, EaselPlugin, Flip, InertiaPlugin, ScrollTrigger, ScrollSmoother, ScrollToPlugin, SplitText, TextPlugin, RoughEase, ExpoScaleEase, SlowMo, CustomEase, CustomBounce, CustomWiggle.
