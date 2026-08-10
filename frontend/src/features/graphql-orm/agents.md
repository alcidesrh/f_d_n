# Walkthrough: GraphQL-ORM (Vue 3 + API Platform 4 Dynamic Client)

Implemented a framework-agnostic core (`@graphql-orm/core`) and Vue 3 integration package (`@graphql-orm/vue`) for dynamic, runtime introspection-driven GraphQL ORM operations against Symfony 8 / API Platform 4.

Full requirements details: [requirements.md](./requirements.md)

## Key Accomplishments

### 1. Framework-Agnostic Core (`@graphql-orm/core`)

- **Introspection Sources** ([introspection-source.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/introspection/introspection-source.ts)):
  - `LiveIntrospectionSource`: queries live endpoint via `getIntrospectionQuery` + `buildClientSchema`.
  - `SdlSnapshotSource`: loads static SDL `schema.graphql` via `buildSchema`.
  - `JsonSnapshotSource`: loads static JSON `introspection.json` via `buildClientSchema`.
- **Schema Parsing & Entity Descriptors** ([entity-descriptor.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/schema/entity-descriptor.ts)):
  - Parses entity metadata and query/mutation field signatures.
  - Automatically detects collection shapes:
    - `relay`: connection with `edges` + `pageInfo` (Relay Cursor Connection).
    - `page`: connection with `collection` + `paginationInfo` (Page-based connection).
    - `flat`: direct list array return type.
  - Extract payload entity field name (e.g. `status` inside `createStatusPayload`).
  - Supports custom operations via `customOperations` escape hatch.
- **IRI Utilities** ([iri-utils.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/schema/iri-utils.ts)):
  - Handled opaque API Platform IRI identifiers (e.g. `/api/statuses/1`) without unnecessary base64 decoding.
- **Selection Set Builder** ([selection-set-builder.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/documents/selection-set-builder.ts)):
  - Dynamic selection query generation with `maxDepth` depth control (default 1) and `include` overrides to prevent circular graph queries.
- **Document Factory** ([document-factory.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/documents/document-factory.ts)):
  - Generates GraphQL `query` and `mutation` documents for `buildItemQuery`, `buildCollectionQuery`, `buildCreateMutation`, `buildUpdateMutation`, `buildDeleteMutation`, and `buildCustomOperation`.
- **Dynamic Schema Validator** ([schema-validator.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/validation/schema-validator.ts)):
  - Constructs `Zod` schemas at runtime directly from GraphQL `InputObject` types.
  - Validates scalar types, enums, required fields, and relation IRIs before sending network requests.
- **Transport & Errors** ([graphql-transport.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/transport/graphql-transport.ts), [errors.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/transport/errors.ts)):
  - `FetchTransport` with middleware support.
  - Flattens Symfony Validator server errors from `extensions.violations` via `GraphQLApiError.violations`.
  - Distinguishes client validation errors (`ClientValidationError`) from backend errors (`GraphQLApiError`).
- **Repository Pattern** ([create-repository.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/core/src/repository/create-repository.ts)):
  - `Repository<T>` providing `findById`, `findAll`, `create`, `update`, `remove`, and `call`.
  - Preserves array ordering (`order: [{ id: 'ASC' }]`).

### 2. Vue 3 Integration Package (`@graphql-orm/vue`)

- **Vue Plugin** ([plugin.ts](file:///home/alcides/f_d_n/frontend/src/features/graphql-orm/vue/src/plugin.ts)):
  - `createGraphQLOrm` plugin providing `GRAPHQL_ORM_KEY` context, repository caching, and schema warm-up.
- **Composables** ([use-collection.ts](file:///home/alcides/f_d_n/frontend/src/composables/use-collection.ts), [use-item.ts](file:///home/alcides/f_d_n/frontend/src/composables/use-item.ts), [use-entity-mutations.ts](file:///home/alcides/f_d_n/frontend/src/composables/use-entity-mutations.ts)):
  - Integrates seamlessly with `@tanstack/vue-query` for caching, loading states, and automatic query cache invalidation on mutations.

### 3. Application Integration & Demo

- **Static Type Mapping** ([entities.ts](file:///home/alcides/f_d_n/frontend/src/entities.ts)): DX entity interfaces (`EntityMap`).
- **UI Demo Component** ([GraphQLOrmDemo.vue](file:///home/alcides/f_d_n/frontend/src/components/GraphQLOrmDemo.vue)): Form & listing UI demonstrating reactive queries, mutations, local validation error handling, and server error handling.
- **Plugin Registration** ([main.ts](file:///home/alcides/f_d_n/frontend/src/main.ts)): Configured Vue app with `VueQueryPlugin` and `createGraphQLOrm`.

---

## Verification Results

### Automated Unit Tests
Executed Vitest test suite (`npm run test:unit -- --run`):
- `collection-shape.spec.ts`: PASSED (3/3 tests: Relay, Page, Flat shapes)
- `document-factory.spec.ts`: PASSED (3/3 tests: queries & mutations)
- `schema-validator.spec.ts`: PASSED (3/3 tests: Zod validation)
- Overall suite: **5 passed test files, 23 passed tests**

### Live Backend End-to-End Smoke Test
Executed `npx jiti smoke-test.ts` against running Symfony 8 / API Platform 4 Docker backend container (`http://localhost/graphql`):
1. **Introspection Load**: Live schema fetched and parsed into `SchemaRegistry`.
2. **`findAll`**: Executed successfully against `/graphql`.
3. **`create`**: Created entity record with returned IRI (`/api/statuses/2`).
4. **`findById`**: Fetched entity record by IRI.
5. **`update`**: Updated entity record label.
6. **`remove`**: Deleted entity record by IRI (`/api/statuses/2`).
- Result: **🎉 ALL SMOKE TESTS PASSED SUCCESSFULLY!**

### Production Build
Executed Vite production bundle build (`npm run build-only`):
- Result: **built in 4.01s with 0 errors**.
