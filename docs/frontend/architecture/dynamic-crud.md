# Sistema de CRUD Dinámico

El CRUD dinámico es el mecanismo central de la aplicación. Permite crear interfaces de listado y formulario para cualquier entidad registrada en el backend sin necesidad de componentes específicos por entidad.

```mermaid
flowchart TD
    A[Navegación a /lista/:entity] --> B[middleware.ts beforeEach]
    B --> C{entityRegistry.getStore(entity)}
    C -->|Store existe| D[Usar store cacheada]
    C -->|No existe| E[Esperar Schema Store cargada]
    E --> F[storeFactory(entity)]
    F --> G{Entidad en schema?}
    G -->|No| H[return false]
    G -->|Sí| I[defineStore dinámico]
    I --> J[init(): fetch config REST]
    J --> K[setColumns() / getFormSchema()]
    K --> L[collection(): GraphQL query]
    L --> M[DynamicCollection.vue render]

    N[Navegación a /form/:entity/:id?] --> O[middleware.ts beforeEach]
    O --> P[getStore(entity)]
    P --> Q[getFormSchema()]
    Q --> R[Si hay id: getItem(id)]
    R --> S[DynamicForm.vue render]
```

## Flujo detallado

### 1. entityRegistry.ts (`src/composables/entityRegistry.ts`)

Actúa como punto de entrada único para obtener stores de entidad. Mantiene un `Map` interno de stores ya creadas.

**Funciones clave:**
- `getStore(entity?)`: Obtiene o crea una store para la entidad. Si no se pasa entity, usa `useRoute().params.entity`. Retorna `false` si la entidad no existe en el schema.
- `waitForSchema()`: Helper que espera a que `schemaStore.isLoaded` sea `true` antes de proceder.

### 2. storeFactory.ts (`src/stores/storeFactory.ts`)

Fábrica que crea stores Pinia en tiempo de ejecución usando `defineStore`. Cada store generada incluye:

**Estado inicial:**
- `name`: nombre de la entidad
- `items`: colección de registros
- `item`: registro individual para formulario
- `options`: opciones para selects relacionados
- `columns`: configuración de columnas del listado
- `visibleColumns`: columnas visibles actualmente
- `filters`: filtros activos
- `formSchema`: schema FormKit generado dinámicamente
- `formData`: datos para selects en formulario
- `pagination`: estado de paginación (si aplica)

**Getters principales:**
- `entity`: retorna la definición de entidad desde schemaStore
- `computedColumns`: columnas filtradas por visibilidad con columna de índice
- `collectionEndpoint`: nombre del endpoint GraphQL (ej: `users`, `statuses`)
- `mutationOperation`: determina si es `create${name}` o `update${name}`
- `collectionVariables`: construye variables GraphQL incluyendo paginación, filtros y orden
- `collectionFields`: construye los fields GraphQL basados en las columnas visibles

**Acciones principales:**
- `init(refresh?)`: carga configuración de entidad desde REST `GET /entity_configurations`
- `collection(force?)`: ejecuta query GraphQL para obtener la colección paginada usando `gql-query-builder`
- `getItem(id)`: obtiene un item individual por ID
- `getFormSchema(refresh?)`: construye el schema FormKit a partir de `config.formFields`, mapeando tipos GraphQL a inputs FormKit
- `submit()`: ejecuta mutation de creación o actualización
- `remove(item)`: mutation de eliminación con confirmación vía Quasar Dialog
- `removeMultiple(items)`: eliminación masiva vía mutation `deleteAgnostic`
- `getOptions()`: carga opciones para selects de entidades relacionadas
- `orderColumns(i, to)`: reordenamiento de columnas
- `setColumns(refresh?)`: inicializa columnas desde la configuración, creando schemas de filtro para cada columna

### 3. DynamicCollection.vue (`src/components/crud/collection/DynamicCollection.vue`)

Componente de listado universal que recibe la store dinámica. Utiliza `q-table` de Quasar con:
- Columnas dinámicas desde `store.computedColumns`
- Paginación server-side vía `store.pagination`
- Filtros en línea usando FormKit
- Selección múltiple para eliminación masiva
- Modo grid en pantallas xs
- Preload skeleton (`ListPreload`) mientras carga

### 4. DynamicForm.vue (`src/components/crud/form/DynamicForm.vue`)

Componente de formulario universal que renderiza el schema FormKit generado por `store.getFormSchema()`. Características:
- Validación automática según tipos de campo
- Campos relacionados se cargan como selects con opciones dinámicas
- Botones CRUD (guardar, eliminar, cancelar) vía slot `CrudButton`
- Soporte para creación y edición
- Post-submit: redirige a lista o resetea formulario

### 5. Persistencia

Las stores dinámicas persisten su estado vía `pinia-plugin-persistedstate` con configuración en `src/stores/persist.ts`. Campos persistidos: `name`, `fields`, `filters`, `visibleColumns`, `columns`, `computedColumns`, `orderField`, `orderType`, `pagination`, `formSchema`, `options`, `config`.

### Configuración de entidad

La configuración visual de cada entidad se almacena en el backend como `EntityConfiguration` y se obtiene vía REST en `init()`. Incluye:
- `collectionFieldConfig`: qué campos mostrar, en qué orden, visibilidad, si son filtrables/ordenables
- `formFields`: qué campos incluir en el formulario, grupos, atributos adicionales

Esta configuración es editable desde la interfaz de administración (`EntityConfigurationEditor.vue` y páginas relacionadas).
