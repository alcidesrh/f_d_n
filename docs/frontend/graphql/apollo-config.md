# Configuración de Apollo Client

**Archivo**: `src/boot/apollo.ts` — 78 líneas

## Creación del cliente

```typescript
const httpLink = new HttpLink({
  uri: config.ENTRYPOINT_GRAPHQL,  // http://localhost/graphql
  fetch: createProfilerFetch(),
})
```

### Profiler Fetch

Intercepta todas las respuestas para capturar el header `X-Debug-Token` de Symfony:

```typescript
function createProfilerFetch() {
  return async (uri, options) => {
    const response = await fetch(uri, options)
    const token = response.headers.get('X-Debug-Token')
    if (token) useProfilerStore().setToken(token)
    return response
  }
}
```

## Cadena de links

Orden de ejecución (de primero a último):

```
removeTypenameLink → queryLink → mutationLink → authLink → errorLink → loadingLink → httpLink
```

## Opciones por defecto

```typescript
defaultOptions: {
  watchQuery: { fetchPolicy: 'no-cache' },
  query: { fetchPolicy: 'cache-first' },
  mutate: { errorPolicy: 'all' },
}
```

## Cache

```typescript
cache: new InMemoryCache({
  typePolicies: {
    EntityConfiguration: { keyFields: ['entityClass'] },
  },
})
```

La única política de tipos personalizada es para `EntityConfiguration`, que usa `entityClass` como campo clave.

## Exposición global

```typescript
const store = useApolloStore()
store.setClient(apolloClient)
app.provide(DefaultApolloClient, apolloClient)

if (typeof window !== 'undefined') {
  window.__APOLLO_CLIENT__ = apolloClient
}
```

El cliente se guarda en `useApolloStore()`, se provee vía Vue's `provide` para `@vue/apollo-composable`, y se expone globalmente para depuración.
