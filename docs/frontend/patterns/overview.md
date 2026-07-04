# Patrones de Diseño

El frontend utiliza varios patrones de diseño modernos de Vue 3 y Composition API para lograr una arquitectura dinámica y mantenible.

## Catálogo de patrones

### Factory
La `storeFactory` en `src/stores/storeFactory.ts` implementa el patrón **Factory** para crear stores Pinia en tiempo de ejecución. Cada entidad registrada en el schema GraphQL obtiene una store con operaciones CRUD completas generadas dinámicamente.

**Uso**: `storeFactory('Bus')` retorna una store Pinia con estado, getters y acciones para la entidad `Bus`.

### Registry
`entityRegistry` en `src/composables/entityRegistry.ts` implementa el patrón **Registry**. Mantiene un `Map` de stores creadas y actúa como punto de acceso único. El método `getStore()` verifica si la store ya existe en el mapa; si no, la crea via `storeFactory`, la inicializa y la registra.

**Ubicación**: `src/composables/entityRegistry.ts`

### Composable
La aplicación hace uso intensivo de composables (Composition API) para encapsular lógica reutilizable:

| Composable | Propósito |
|-----------|-----------|
| `useBreadcrumbs` | Genera breadcrumbs desde la ruta activa |
| `usePermission` | Verificación de permisos planos |
| `useApiRest` | Cliente REST con fetch |
| `useEntityConfig` | CRUD de configuración de entidades vía GraphQL |
| `useIcons` | Búsqueda y categorización de iconos |
| `useGsap` | Inicialización de GSAP con plugins |
| `useBreakpoint` | Breakpoints reactivos |
| `useRouter` / `useRoute` | Acceso tipado al router |
| `useNotifications` | Notificaciones Quasar |

### Composition API
Toda la lógica de componentes usa `<script setup lang="ts">` con Composition API. No se usan Options API. El estado global usa `defineStore` con sintaxis de función (setup stores) para las stores modernas, y sintaxis de objeto para schemaStore.

**Ejemplo de setup store** (`session.ts`):
```typescript
export const useUserSessionStore = defineStore('userSession', () => {
  const user = ref<User | null>()
  const token = ref<string | null>(null)
  // ...
  return { user, token, login, clear, can, ... }
}, { persist: { pick: ['user', 'token', 'permissions'] } })
```

### Auto-import
`unplugin-auto-import` auto-importa automáticamente APIs de Vue, Vue Router, Pinia, y todos los archivos de:
- `src/composables/`
- `src/stores/autoimport/**/*`
- `src/utils/autoimport/**/*`
- `src/config/`
- `src/graphql/`
- `src/services/`

Esto elimina importaciones manuales para hooks como `ref`, `computed`, `watch`, `useRouter`, `defineStore`, etc.

### Dynamic Components
Los componentes `DynamicCollection.vue` y `DynamicForm.vue` usan el patrón de **componentes dinámicos**: reciben una store creada en tiempo de ejecución y renderizan UI genérica basada en la configuración de la entidad.

### Event Bus
`src/services/bus.ts` usa el `EventBus` de Quasar para comunicación entre componentes no relacionados:
```typescript
bus.emit('positive', 'Operación exitosa')
bus.emit('error', mensaje)
```

### Provider/Inject
El servicio responsive se provee via `app.provide('responsive', responsiveService.state)` en el boot, y se consume con `inject('responsive')` en `useBreakpoint`.

### Store-to-refs
Se usa `storeToRefs` de Pinia para extraer estado reactivo de stores manteniendo la reactividad:
```typescript
const { mode, modeStates } = storeToRefs(sidebarStore)
```
