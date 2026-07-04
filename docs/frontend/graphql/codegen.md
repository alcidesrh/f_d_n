# Schema GraphQL y Generación de Código

## Schema GraphQL

El schema se obtiene mediante **introspección GraphQL** en `schemaStore.loadEntities()`. La query de introspección recorre todos los tipos, campos, argumentos y tipos de retorno.

```graphql
query IntrospectionQuery {
  __schema {
    types {
      ...FullType
    }
  }
}
fragment FullType on __Type {
  ...TypeRef
  fields { name args { ...InputValue } type { ...TypeRef } }
  possibleTypes { ...TypeRef }
  inputFields { ...InputValue }
}
fragment TypeRef on __Type { kind name ofType { kind name ofType { ... } } }
```

## Sin generación de tipos

El proyecto **no usa herramientas de generación de tipos** como GraphQL Code Generator. Las razones:

1. **Queries dinámicas**: Las queries y mutations se construyen en tiempo de ejecución con `gql-query-builder`, no están escritas estáticamente como strings GraphQL. No hay archivos `.graphql` estáticos para generar tipos.

2. **Schema dinámico**: Las entidades disponibles dependen de la configuración del backend. No hay un schema fijo.

3. **Tipos manuales**: Los tipos TypeScript se definen manualmente en `src/types/graphql.ts`.

## gql-query-builder

Librería que construye queries GraphQL programáticamente:

```typescript
import * as queryBuilder from 'gql-query-builder'

// Query
const qb = queryBuilder.query({
  operation: 'users',
  variables: { currentPage: { type: 'Int!', value: 1 } },
  fields: ['id', 'name', { role: ['id', 'label'] }],
})

// Mutation
const qb = queryBuilder.mutation({
  operation: 'createUser',
  variables: { input: { type: 'createUserInput!', value: data } },
  fields: ['clientMutationId'],
})
```

## graphql-tag

Se usa `graphql-tag` para parsear strings GraphQL manuales en casos específicos:

1. **Introspection query**: en `schemaStore.ts`
2. **Queries manuales**: en `useEntityConfig.ts` (consultas de configuración)

## Tipos TypeScript

Definidos en `src/types/graphql.ts`:

```typescript
interface Entity { name, fields, queries, mutations, pagination }
interface Column { id, field, position, visible, sortable, filterable, label, schema }
interface StateStore { name, items, item, config, columns, filters, formSchema, ... }
interface SchemaStore { entities, types }
interface Field { name, input, type, relatedTo }
```

Estos tipos se usan en las stores dinámicas y componentes CRUD.
