# Store Factory

**Archivo**: `src/stores/storeFactory.ts` — 531 líneas

La Store Factory es el mecanismo central para crear stores Pinia en tiempo de ejecución para entidades registradas en el schema GraphQL.

## Funcionamiento

```typescript
export default async (name: string) => {
  const schema = useSchemaStore()
  if (typeof schema.entities[name] == 'undefined') return false
  // ... defineStore con estado, getters y acciones dinámicos
  return await defineStore(name, { ... })()
}
```

## Estado generado

Cada store incluye estado para colección, formulario, configuración, paginación y filtros. Ver `patterns/dynamic-crud.md` para detalles completos del estado.

## Getters

| Getter | Descripción |
|--------|-------------|
| `entity` | Definición de entidad desde schemaStore |
| `computedColumns` | Columnas visibles con columna índice |
| `nameDecapitalize` | Nombre en minúscula (ej: `'bus'`) |
| `collectionEndpoint` | Endpoint GraphQL (ej: `'users'`, `'statuses'`) |
| `mutationOperation` | `create${name}` o `update${name}` |
| `iri` | IRI del item actual |
| `collectionVariables` | Variables GraphQL con paginación, filtros, orden |
| `collectionFields` | Campos GraphQL según columnas |
| `computedFormFields` | Campos de formulario visibles |

## Acciones

| Acción | Descripción |
|--------|-------------|
| `init(refresh?)` | Carga config REST |
| `setColumns(refresh?)` | Inicializa columnas con schemas de filtro |
| `collection(force?)` | Query GraphQL paginada |
| `getItem(id)` | Obtiene item individual |
| `getFormSchema(refresh?)` | Genera schema FormKit |
| `submit()` | Mutation create/update |
| `remove(item)` | Eliminación con confirmación |
| `removeMultiple(items)` | Eliminación masiva |
| `getOptions(entities?)` | Carga opciones para selects |
| `orderColumns(i, to)` | Reordenamiento de columnas |

## gql-query-builder

Usa la librería `gql-query-builder` 3.8 para construir queries GraphQL dinámicamente:

**Query de colección**:
```typescript
queryBuilder.query({
  operation: 'users',
  variables: { currentPage: { type: 'Int!', value: 1 } },
  fields: [{ collection: ['id', 'name'] }, { paginationInfo: ['totalCount'] }],
})
```

**Mutation**:
```typescript
queryBuilder.mutation({
  operation: 'createUser',
  variables: { input: { type: 'createUserInput!', value: data } },
  fields: ['clientMutationId'],
})
```

## Persistencia

Las stores dinámicas persisten configuración visual, filtros y preferencias de UI. Los datos (`items`, `item`) no se persisten.

## Registro

El `entityRegistry` (`src/composables/entityRegistry.ts`) mantiene un `Map` de stores creadas y delega en `storeFactory` para la creación.
