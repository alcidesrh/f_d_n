# Schema Store

**Archivo**: `src/stores/autoimport/schemaStore.ts` — 403 líneas

La Schema Store almacena los metadatos de todas las entidades del sistema, obtenidos mediante introspección del schema GraphQL.

## Estado

```typescript
state: (): SchemaStore => ({
  editables: [
    'Action', 'Role', 'Permiso', 'Status', 'ApiToken', 'Usuario',
    'Localidad', 'Nacion', 'Asiento', 'Bus', 'Empresa', 'Piloto',
    'BusMarca', 'Boleto', 'Salida', 'Trayecto', 'Enclave', 'Cliente',
    'Estacion', 'Parada', 'Venta', 'Recorrido', 'Icon', 'IconCategory',
  ],
  entities: {},  // Record<string, Entity>
  types: {},     // Record<string, string[]>
})
```

- `editables`: lista de entidades que pueden ser gestionadas via CRUD
- `entities`: definiciones completas de entidad (campos, queries, mutations)
- `types`: tipos auxiliares (pagination info, payloads, etc.)

## Getter

- `isLoaded`: `Object.keys(entities).length != 0` — indica si la introspección se completó

## Acción principal: `loadEntities()`

Ejecuta una query de introspección GraphQL:

```graphql
query IntrospectionQuery {
  __schema {
    types { ...FullType }
  }
  fragment FullType on __Type {
    ...TypeRef
    fields { name args { ...InputValue } type { ...TypeRef } }
    possibleTypes { ...TypeRef }
    inputFields { ...InputValue }
  }
}
```

### Procesamiento: `setEntities(schema)`

1. Encuentra los tipos que implementan la interface `Node` (vía `possibleTypes`)
2. Filtra por `editables`
3. Para cada entidad, extrae campos con su tipo GraphQL, relaciones y configuración de input
4. Construye queries (collection + item) con sus argumentos y tipos de retorno
5. Construye mutations (create, update, delete) con sus argumentos de input

### Estructura de entidad generada

```typescript
entities['Bus'] = {
  name: 'Bus',
  fields: {
    id: { name: 'id', type: 'ID', input: { $formkit: 'number', label: 'id' } },
    marca: { name: 'marca', type: 'OBJECT', relatedTo: 'BusMarca', input: { $formkit: 'select', options: '$busMarcas' } },
    // ...
  },
  queries: {
    collection: { name: 'buses', args: { currentPage: 'Int!', ... }, type: 'BusPageConnection', fields: [...] },
    item: { name: 'bus', args: { id: 'ID!' }, type: 'Bus' },
  },
  mutations: {
    create: { args: { input: { type: 'createBusInput!' } } },
    update: { args: { input: { type: 'updateBusInput!' } } },
    delete: { args: { input: { type: 'deleteBusInput!' } } },
  },
  pagination: true,  // si existe BusPageConnection
}
```

### Mapeo de tipos a inputs FormKit

| Tipo GraphQL | Input FormKit |
|-------------|--------------|
| `String` | `text` |
| `Int`, `Float`, `ID` | `number` |
| `Boolean` | `checkbox` |
| `Date` | `datetime` |
| Relación `OBJECT` | `select` (options: `$related`) |
| Relación `LIST` | `select` multiple |
| `ENUM` | `select` |

### `setTypes(schema)`

Procesa todos los tipos del schema excluyendo entidades editables, queries, mutations y tipos escalares. Almacena los nombres de campos de cada tipo auxiliar.

## Persistencia

```typescript
persist: { pick: ['entities', 'types'] }
```

Los metadatos se persisten en localStorage para evitar reintrospección en cada carga.

## Uso

```typescript
const schemaStore = useSchemaStore()
await schemaStore.loadEntities()
const busEntity = schemaStore.entities['Bus']
```
