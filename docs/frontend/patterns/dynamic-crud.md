# Patrón de CRUD Dinámico — storeFactory.ts

El archivo `src/stores/storeFactory.ts` (531 líneas) es el corazón del sistema de CRUD dinámico. Implementa el patrón **Factory** para generar stores Pinia completas en tiempo de ejecución para cualquier entidad registrada en el schema GraphQL.

## Funcionamiento interno

### Firma

```typescript
export default async (name: string) => {
  const schema = useSchemaStore()
  if (typeof schema.entities[name] == 'undefined') return false
  // ...
  return await defineStore(name, { ... })()
}
```

Recibe el nombre de una entidad (ej: `'Bus'`, `'Usuario'`, `'Role'`). Si la entidad no existe en el schema, retorna `false`.

### Estado generado

Cada store dinámica contiene:

```typescript
const state = {
  name: name,
  config: {},              // Configuración visual (desde REST)
  items: [],               // Colección de registros
  item: {},                // Item individual (formulario)
  options: [],             // Opciones para selects relacionados
  excludeFields: ['legacyId'],
  labels: [{ createdAt: 'Fecha' }, { updatedAt: 'Actualizado' }],
  columns: [],             // Configuración de columnas del listado
  visibleColumns: [],      // Columnas visibles actualmente
  filters: {},             // Filtros activos
  orderField: 'id',        // Campo de ordenación
  orderType: 'DESC',       // Dirección de orden
  formSchema: [],          // Schema FormKit generado
  formData: {},            // Datos adicionales para el formulario
  formGroups: [],          // Grupos de campos del formulario
  pagination: undefined,   // Estado de paginación (si aplica)
}
```

La paginación se activa solo si la entidad tiene un tipo `XxxPageConnection` en el schema.

### Getters dinámicos

**`entity`**: Retorna la definición de entidad desde `schemaStore.entities[name]`, incluyendo campos, queries y mutations.

**`computedColumns`**: Filtra `columns` por visibilidad y antepone la columna de índice `#`.

**`collectionEndpoint`**: Construye el endpoint GraphQL (ej: `statuses` para `Status`, `users` para `User`).

**`mutationOperation`**: Determina la operación de mutation: `create${name}` o `update${name}` según si `item.id` existe.

**`collectionVariables`**: Construye variables GraphQL incluyendo:
- Paginación (`currentPage`, `itemsPerPage`)
- Filtros por columna (incluyendo filtros por relación)
- Ordenación (`order: [{ campo: type }]`)

**`collectionFields`**: Construye los campos GraphQL dinámicamente según las columnas visibles. Las relaciones se expanden como `{ relatedField: ['id', 'label'] }`.

### Acciones generadas

**`init(refresh?)`**: Obtiene configuración REST desde `GET /entity_configurations?entityClass=${name}`. Si `refresh`, recarga columnas y schema de formulario.

**`setColumns(refresh?)`**: Inicializa columnas desde `config.collectionFieldConfig`. Para cada columna filtrable, crea un schema FormKit con el tipo de input adecuado (text_search, select, date range).

**`collection(force?)`**: Ejecuta query GraphQL construido con `gql-query-builder`. Soporta dos formatos de respuesta:
- Con paginación: `{ collection: [...], paginationInfo: {...} }`
- Sin paginación: array directo

**`getItem(id)`**: Obtiene un item por ID construyendo la query con los campos del formulario. Las relaciones se expanden a `{ field: ['label', 'id'] }`.

**`getFormSchema(refresh?)`**: Construye el schema FormKit para el formulario dinámico:
1. Toma los campos de `config.formFields`
2. Para cada campo, obtiene el tipo desde `entity.fields[field]`
3. Si el campo tiene `relatedTo`, carga opciones via `getStore(relatedTo).getOptions()`
4. Genera schema con grid responsivo (`grid-cols-1 md:grid-cols-2`)
5. Incluye slot para `CrudButton`

**`submit()`**: Ejecuta mutation de creación o actualización. Usa `gql-query-builder` para construir la mutation con el input completo.

**`remove(item)`**: Mutation de eliminación con diálogo de confirmación Quasar. Post-eliminación: recarga colección y redirige a lista.

**`removeMultiple(items)`**: Eliminación masiva via mutation `deleteAgnostic` que recibe un array de IDs y el nombre del recurso.

**`getOptions(entities?)`**: Carga opciones para selects de entidades relacionadas. Usa query `collectionAgnostic` con parámetro `resource`. Soporta carga masiva de múltiples entidades.

## gql-query-builder

La librería `gql-query-builder` (versión 3.8) se usa para construir queries y mutations GraphQL dinámicamente:

```typescript
const qb = queryBuilder.query({
  operation: 'users',
  variables: { currentPage: { type: 'Int!', value: 1 } },
  fields: [{ collection: ['id', 'name', 'email'] }, { paginationInfo: ['totalCount'] }],
})
// Resultado: query getUsers($currentPage: Int!) { users(currentPage: $currentPage) { collection { id name email } paginationInfo { totalCount } } }
```

## Persistencia

La persistencia se configura en `src/stores/persist.ts`:
```typescript
export default {
  pick: ['name', 'filters', 'visibleColumns', 'columns', 'orderField', 'orderType', 'pagination', 'formSchema', 'options', 'config'],
}
```

Solo se persisten campos de configuración y UI, no los datos (`items`, `item`).

## Flujo de creación

```mermaid
flowchart TD
    A[getStore('Bus')] --> B{Store en registry?}
    B -->|Sí| C[Retornar store existente]
    B -->|No| D{Store en Pinia state?}
    D -->|Sí| E[defineStore('Bus')()]
    D -->|No| F[waitForSchema]
    F --> G[storeFactory('Bus')]
    G --> H{Entidad en schema?}
    H -->|No| I[return false]
    H -->|Sí| J[defineStore dinámico]
    J --> K[init: fetch config REST]
    K --> L[Registrar en Map stores]
    L --> M[Retornar store]
```
