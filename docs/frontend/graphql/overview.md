# GraphQL — Apollo Client 4

## Estrategia

GraphQL es la **capa primaria de datos** del frontend. El backend expone un endpoint GraphQL en `config.ENTRYPOINT_GRAPHQL` (`http://localhost/graphql`).

**Cuándo usar GraphQL vs REST:**

| Criterio | GraphQL | REST |
|----------|---------|------|
| Operaciones CRUD | Colecciones, items, mutations | ❌ |
| Autenticación | ❌ | Login, permisos |
| Configuración de entidad | ❌ | Entity configurations |
| Versiones de configuración | ❌ | Config versions |
| Tiempo real | Mercure (SSE) | ❌ |

## Configuración del cliente

El cliente Apollo se configura en `src/boot/apollo.ts`:

```typescript
const apolloClient = new ApolloClient({
  assumeImmutableResults: true,
  connectToDevTools: true,
  link: ApolloLink.from([
    removeTypenameLink,
    createQueryLink(),
    createMutationLink(),
    createAuthLink(),
    createErrorLink(),
    createLoadingLink(pinia),
    httpLink,
  ]),
  cache: new InMemoryCache({
    typePolicies: {
      EntityConfiguration: { keyFields: ['entityClass'] },
    },
  }),
  queryDeduplication: false,
  defaultOptions: {
    watchQuery: { fetchPolicy: 'no-cache' },
    query: { fetchPolicy: 'cache-first' },
    mutate: { errorPolicy: 'all' },
  },
})
```

## Políticas de caché

- **watchQuery**: `no-cache` — las suscripciones siempre consultan la red
- **query**: `cache-first` — queries manuales usan caché si está disponible
- **mutate**: `errorPolicy: 'all'` — captura errores de GraphQL y de red

## Cache

`InMemoryCache` con una sola política de tipos: `EntityConfiguration` usa `entityClass` como key en lugar del `id` por defecto. No se usa `IntrospectionFragmentMatcher`.

## Apollo Store

El wrapper `useApolloStore` (`src/stores/autoimport/useApolloStore.ts`) provee acceso al cliente:

```typescript
const apollo = useApolloStore()
await apollo.query({ query, variables })
await apollo.mutate({ mutation, variables })
```

También expone helpers:
- `createLazy(query, options)`: lazy query con `useLazyQuery`
- `runQuery(query, variables, options)`: query reactiva con `useQuery`

## gql-query-builder

Las queries dinámicas se construyen con `gql-query-builder` 3.8, que permite construir queries GraphQL programáticamente:

```typescript
import * as queryBuilder from 'gql-query-builder'

const qb = queryBuilder.query({
  operation: 'users',
  variables: { currentPage: { type: 'Int!', value: 1 } },
  fields: [{ collection: ['id', 'name'] }, { paginationInfo: ['totalCount'] }],
})
// → query getUsers($currentPage: Int!) { users(currentPage: $currentPage) { collection { id name } paginationInfo { totalCount } } }
```

## Type generation

No se usa generación de tipos a partir del schema GraphQL. Los tipos se definen manualmente en `src/types/graphql.ts`. Las queries y mutations se construyen dinámicamente, por lo que la generación estática de tipos no es práctica.
