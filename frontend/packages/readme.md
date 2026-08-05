# GraphQL-ORM (Vue 3 + API Platform 4)

Cliente ORM dinámico para SPA Vue 3 que parsea la introspección GraphQL de un backend Symfony 8 / API Platform 4 **en runtime** y construye en memoria repositorios tipados por entidad (queries, mutaciones, argumentos y validación) — sin generación de código.

Implementación de la tarea `tarea_GraphQL-ORM_cliente_dinámico_basado_en_introspección_para_Vue3__APIPlatform_4_Symfony.md`. Reporte de implementación: `agents/walkthrough.md`.

**Estado de la verificación** (este documento se genera tras auditarla):

- Unit tests: **50 passed / 4 skipped** (los skipped son opt-in de RAGF, ajenos a este paquete).
- Smoke test contra backend real (`http://localhost/graphql`): `findAll` → `create` → `findById` → `update` → `remove`, todo OK.
- `npm run build-only`: 0 errores.
- `npm run type-check`: **pasa limpio**. (Los errores pre-existentes de `Fk*.vue`/`Dashboard.vue` fueron reparados como parte de esta auditoría.)

---

## 1. Qué resuelve

Un componente Vue **no escribe ninguna query GraphQL a mano**. En su lugar:

```ts
const { data, isLoading, error } = useCollection<EntityMap['Status']>('Status');
const { create, remove } = useEntityMutations('Status');

await create({ nombre: 'ACTIVO', label: 'Estado activo' }); // se valida contra createStatusInput antes de golpear la red
```

Internamente el motor:

1. Descarga e interpreta la **introspección** (o un snapshot) del endpoint.
2. Construye un **registro de entidades** (`SchemaRegistry`) que describe las operaciones reales de cada tipo: `Query.status` / `Query.statuss`, `Mutation.createStatus/updateStatus/deleteStatus`, argumentos, tipos de retorno, forma de paginación.
3. Genera los **documentos GraphQL** (selection sets con control de profundidad) y los **validadores Zod** a partir de los `InputObject` reales.
4. Ejecuta vía `fetch` (con middleware componible) y **separa** errores de validación local (`ClientValidationError`) de errores del backend (`GraphQLApiError.violations`).

El principio rector es: **todo se deriva de la introspección, nunca de convenciones asumidas**. El motor detecta la forma real de paginación (Relay/page/flat), trata los IDs como IRI opacos, y respeta que los tipos puedan diferir entre operación y operación (grupos de serialización).

---

## 2. Estructura

```
frontend/
  packages/
    graphql-orm-core/          # @graphql-orm/core   — núcleo SIN dependencia de Vue
      src/
        introspection/introspection-source.ts
        schema/entity-descriptor.ts
        schema/schema-registry.ts
        schema/iri-utils.ts
        documents/selection-set-builder.ts
        documents/document-factory.ts
        validation/schema-validator.ts
        transport/errors.ts
        transport/graphql-transport.ts
        repository/create-repository.ts
        index.ts
      test/                    # vitest (fixtures SDL inline, sin red)
    graphql-orm-vue/           # @graphql-orm/vue    — integración Vue 3 + TanStack Query
      src/
        plugin.ts
        composables/use-collection.ts
        composables/use-item.ts
        composables/use-entity-mutations.ts
        index.ts
  src/
    entities.ts                # EntityMap: tipos estáticos por entidad (capa DX)
    main.ts                    # app.use(VueQueryPlugin); app.use(createGraphQLOrm(...))
    components/GraphQLOrmDemo.vue   # demo funcional (ruta /demo-graphql)
  smoke-test.ts                # smoke test E2E contra backend real (npx jiti smoke-test.ts)
```

> **Importante:** estos paquetes **no son paquetes npm reales** (no hay `package.json` dentro de `packages/`). Se exponen como módulos fuente vía alias:
> - `vite.config.ts`: `@graphql-orm/core` → `./packages/graphql-orm-core/src/index.ts`, `@graphql-orm/vue` → `./packages/graphql-orm-vue/src/index.ts`
> - `tsconfig.app.json`: `paths` equivalentes.
>
> La capa `vue` importa el core por rutas relativas (`../../graphql-orm-core/src/...`), no por el alias, para que el alias no se resuelva en círculo.

---

## 3. Características del core (`@graphql-orm/core`)

### 3.1 Fuentes de introspección — `IntrospectionSource`

Interfaz `load(): Promise<GraphQLSchema>` con tres implementaciones intercambiables:

| Clase | Fuente | Uso recomendado |
|---|---|---|
| `LiveIntrospectionSource(endpoint, headers?, fetchImpl?)` | `POST` con `getIntrospectionQuery()` → `buildClientSchema` | Desarrollo: refleja cambios del schema al instante |
| `SdlSnapshotSource(sdlUrl, fetchImpl?)` | `fetch` de `schema.graphql` (SDL) → `buildSchema` | Producción, introspección apagada |
| `JsonSnapshotSource(jsonUrl, fetchImpl?)` | `fetch` de `introspection.json` → `buildClientSchema` | Producción, si prefieres JSON |

**Propósito:** resolver la tensión introspección viva vs. desactivada en producción (§3.9 de la spec) sin bifurcar el motor: `SchemaRegistry` solo consume el `GraphQLSchema` resultante, no sabe de dónde vino.

```ts
// generar el snapshot en el backend (Fase 5 de la spec) y publicarlo como asset:
//   docker exec backend php bin/console api:graphql:export -o /tmp/schema.graphql
//   docker cp backend:/tmp/schema.graphql frontend/public/schema.graphql
const source = new LiveIntrospectionSource('http://localhost/graphql');
const schema = await source.load();
```

### 3.2 Registro de entidades — `SchemaRegistry` + `buildEntityDescriptor`

- `new SchemaRegistry(schema)` — cachea los `EntityDescriptor` por tipo (`describe(typeName)` con memoización; `warmUp([...])` pre-construye los configurados).
- `buildEntityDescriptor(schema, 'Book')` produce un `EntityDescriptor`:

```ts
interface EntityDescriptor {
  typeName: string;
  queries: { item?: OperationDescriptor; collection?: OperationDescriptor };
  mutations: { create?; update?; delete?: OperationDescriptor };
  customOperations: OperationDescriptor[]; // cualquier query/mutation extra detectada
}
```

Cada `OperationDescriptor` resuelve **de forma independiente** su `returnTypeName`/`returnFields` (porque con grupos de serialización el ítem, el nodo de colección y el payload de mutación pueden ser tipos distintos — `BookItem` vs `BookCollection` vs `BookPayloadData`), su `args` (`name`, `typeName`, `printedType` — el tipo tal cual para declarar variables, `isNonNull`, `isList`), y para mutaciones `inputTypeName`/`inputFields`/`payloadEntityField` (el campo del payload que trae la entidad, p. ej. `book` dentro de `createBookPayload`).

**Detección de forma de colección** (`collectionShape`): inspecciona los campos del tipo de retorno de la query de colección:

| Shape | Señales en el tipo de retorno | Se asume cuando |
|---|---|---|
| `relay` | campos `edges` + `pageInfo` | paginación por cursor (default API Platform) |
| `page` | campos `collection` + `paginationInfo` | `paginationType: 'page'` |
| `flat` | el retorno YA es la lista de nodos | `paginationEnabled: false` |

`nodeTypeName` se resuelve igualmente (desde el campo `node` de los edges, desde `collection`, o del tipo desenvuelto).

**Operaciones custom** (`customOperations`): cualquier campo de `Query`/`Mutation` que no sea el CRUD estándar y cuyo tipo de retorno empiece con el nombre de la entidad se registra y queda accesible vía `repository.call()` — escape hatch para resolvers propios (§3.1).

### 3.3 Utilidades de IRI — `iri-utils.ts`

El `id` de API Platform **es el IRI** (p. ej. `/api/statuses/3`), no un ID Relay en base64. No hay que decodificar nada.

```ts
shortId('/api/statuses/3')            // '3'
resourceTypeFromIri('/api/statuses/3') // 'statuses'
```

### 3.4 Constructor de selection sets — `buildSelectionSet(registry, typeName, options?)`

Genera la selección de campos para un tipo leyendo sus campos directamente del `GraphQLObjectType`.

- **`maxDepth`** (default `1`): limita la expansión de relaciones a N niveles para evitar ciclos (`Book.author.books.author...`). Al llegar al límite, los objetos con campo `id` se reducen a `{ id }`; los que no lo tienen (p. ej. `paginationInfo`) seleccionan sus campos escalares.
- **`include`**: override por campo para pedir más profundidad, `true` o un objeto de opciones anidado.

```ts
buildSelectionSet(registry, 'Book', {
  include: { author: { include: { books: { maxDepth: 2 } } } },
});
```

### 3.5 Fábrica de documentos — `document-factory.ts`

`buildItemQuery`, `buildCollectionQuery`, `buildCreateMutation`, `buildUpdateMutation`, `buildDeleteMutation`, `buildCustomOperation`. Cada uno devuelve `{ document, op }` y construye las declaraciones/uso de variables (`$first: Int`, `first: $first`) desde `op.args`, adaptando el body según `collectionShape`:

```graphql
# relay  → edges { cursor node { … } } pageInfo { … } totalCount
# page   → collection { … } paginationInfo { … }
# flat   → selección directa del nodo
# mutation → { book { … } clientMutationId }
```

### 3.6 Validador dinámico — `schema-validator.ts`

Construye un `z.object` en runtime a partir de los `inputFields` de un `InputObject`:

- `String`/`ID` → `z.string()`, `Int` → `z.number().int()`, `Float` → `z.number()`, `Boolean` → `z.boolean()`, `DateTime`/`Date` → `z.string()` con `refine` de ISO 8601.
- Enums → `z.enum([...valores])` leídos del schema.
- Campos tipo **`object`** (relación) → `z.string()` que debe empezar por `/` (**IRI**, §3.7). Los campos tipo **`input-object`** (relación anidada permitida) → `z.object(...)` **recursivo**: valida subcampos contra el `InputObject` real (incluidos los `NonNull` internos), con guard de ciclos para inputs autorreferentes (el bucle se corta con `z.unknown()`).
- Aplica `z.array(...)` si `isList` y `.nullish()` si no es `NonNull`.
- `clientMutationId` se omite (lo gestiona el motor).
- **`registerScalar('MiScalar', () => z.custom(...))`** extiende el mapa para scalars custom del backend.

> Nota de comportamiento: en un InputObject real de API Platform, una relación se tipa como escalar `String`/`ID` (el IRI). El validador trata esos campos como texto normal (no les exige empezar por `/`) para no rechazar campos de texto legítimos como `nombre: String`. La rama IRI (tipo `object`) solo se dispara con descriptores construidos explícitamente como tal.

```ts
const { validate } = createInputValidator(registry, op.inputFields ?? []);
const result = validate(input); // result.success | result.error.issues
```

### 3.7 Transporte y errores — `graphql-transport.ts`, `errors.ts`

- `GraphQLTransport` interfaz de 5 líneas (`execute<T>(document, variables)`): se puede reemplazar `FetchTransport` por urql/Apollo detrás de ella sin tocar el core.
- `FetchTransport.use(mw)` encadena middleware en el patrón *link chain* (inspirado en Apollo Links). El último eslabón hace el POST a `/graphql` y **fusiona los `headers` que los middleware añadieron a la petición** con `Content-Type: application/json`.
- **`authMiddleware(getToken)`** adjunta `Authorization: Bearer <token>` solo cuando hay token.
- **`headersMiddleware(getHeaders)`** adjunta headers arbitrarios por request (X-Tenant, Accept-Language, etc.). `createGraphQLOrm({ authHeaders })` ya la registra automáticamente.

Errores, distinguibles con `instanceof`:

| Error | Significado | Uso típico |
|---|---|---|
| `ClientValidationError` | Falló la validación Zod local, antes de la red | Marcar campos de formulario al instante (`e.issues`) |
| `GraphQLApiError` | El backend rechazó la operación | `e.violations` aplana `extensions.violations` de Symfony Validator → mapeo por `propertyPath` |
| `GraphQLOrmError` | Clase base | — |

### 3.8 Repositorio — `createRepository(registry, transport, typeName)`

API principal del core:

| Método | Firma | Notas |
|---|---|---|
| `findById` | `(id, options?)` | `id` = IRI opaco; `data[op.fieldName]` o `null` |
| `findAll` | `(params?, options?)` | `params.first/after/last/before` (Relay), `order`, `filters` |
| `create` | `(input)` | valida contra `inputFields` → `ClientValidationError`; envía `{ input }` con el **input parseado** por Zod (`result.data`) |
| `update` | `(id, input)` | valida `{ id, ...input }`; envía `{ input: { id, ... } }` con `result.data` |
| `remove` | `(id)` | devuelve `true` |
| `call` | `(operationName, args?, selection?)` | operaciones custom; **desempaqueta `data[op.fieldName]`** (consistente con findById/findAll) y **reenvía `selection`** a `buildSelectionSet` vía `buildCustomOperation` |

`FindAllParams`:

```ts
interface FindAllParams {
  first?: number; after?: string; last?: number; before?: string;
  order?: Array<Record<string, 'ASC' | 'DESC'>>; // LISTA, preserva orden — §3.4
  filters?: Record<string, unknown>;             // argumentos reales del schema (p. ej. _list, anidados con _)
}
```

- **`order` es una lista de objetos de una clave** (`[{ title: 'ASC' }, { nombre: 'DESC' }]`), no un objeto plano — GraphQL `INPUT_OBJECT` no garantiza orden de claves; API Platform usa la lista justamente para preservarlo. El motor la pasa tal cual a las variables.
- `filters` se valida contra los argumentos reales de la query (`assertKnownArgs`): un filtro inexistente lanza error explícito con la lista de válidos.
- Solo se envían variables `!== undefined` **y** declaradas en el schema (`knownArgNames`).
- Normaliza el resultado por shape: `relay` → `{ items, totalCount, pageInfo }`; `page` → `{ items, totalCount, pageInfo }`; `flat` → `{ items }`.

`CollectionResult<T> = { items: T[]; totalCount?: number; pageInfo?: PageInfo }`.

---

## 4. Características de la capa Vue (`@graphql-orm/vue`)

### 4.1 Plugin — `createGraphQLOrm(options)`

```ts
const orm = createGraphQLOrm({
  endpoint: import.meta.env.VITE_GRAPHQL_ENDPOINT || 'http://localhost/graphql',
  source: isProd
    ? new SdlSnapshotSource('/schema.graphql')           // asset en public/ (generado: ver §3.1)
    : new LiveIntrospectionSource(graphqlEndpoint),
  entities: ['Status', 'Empresa', 'Piloto', 'Trayecto', 'Servicio', 'Usuario'],
  // authHeaders?: () => Record<string,string> | Promise<...>
  //   → se registra automáticamente como headersMiddleware en el transporte
});
app.use(VueQueryPlugin).use(orm).mount('#app');
```

- Provee el contexto vía `GRAPHQL_ORM_KEY` (InjectionKey).
- `orm.readyPromise`: se puede `await` en `main.ts` antes de montar, o usar la reactividad `isReady` (los composables no disparan queries hasta `isReady`).
- Cachea `Repository` por entidad (`repoCache`) y hace `warmUp(entities)` al arrancar.
- `ctx.repository<T>(typeName)` lanza error si se usa antes de estar listo.

### 4.2 Composables (sobre `@tanstack/vue-query`)

- **`useCollection<T>(entity, params?)`** → `useQuery` con `queryKey: ['graphql-orm', entity, 'collection', params]`, `enabled: isReady`. `params` puede ser `ref`/`getter` (reactivo).
- **`useItem<T>(entity, id)`** → `queryKey: ['graphql-orm', entity, 'item', id]`, `enabled: isReady && !!id`.
- **`useEntityMutations<T>(entity)`** → `{ create, update, remove }`, y tras cada mutación exitosa **invalida** `['graphql-orm', entity]` (partial match → refresca item + collection). Devuelve el resultado de la mutación.

Cada composable usa `inject(GRAPHQL_ORM_KEY)` y lanza error claro si el plugin no está instalado.

### 4.3 Capa de tipos estáticos (DX) — `EntityMap`

`src/entities.ts` define la interfaz `EntityMap` a mano (o generada desde el snapshot) y los composables se usan con `EntityMap['Status']` para autocompletado. **El motor funciona igual sin ella** (`T = Record<string, unknown>`); es solo DX (§11 de la spec).

---

## 5. Uso end-to-end (ejemplo real del repo)

```vue
<script setup lang="ts">
import { useCollection, useEntityMutations } from '@graphql-orm/vue';
import { ClientValidationError, GraphQLApiError } from '@graphql-orm/core';
import type { EntityMap } from '@/entities';

const { data, isLoading, error } = useCollection<EntityMap['Status']>('Status');
const { create, remove } = useEntityMutations<EntityMap['Status']>('Status');

async function addStatus() {
  try {
    await create({ nombre: statusName.value, label: statusLabel.value || undefined });
  } catch (e) {
    if (e instanceof ClientValidationError) { /* validación local */ }
    else if (e instanceof GraphQLApiError) { e.violations /* map a campos */ }
  }
}
</script>
```

Demo funcional montada en la ruta **`/demo-graphql`** (`src/components/GraphQLOrmDemo.vue`): lista de `Status` + formulario de creación + borrado + manejo de `ClientValidationError`/`GraphQLApiError.violations`.

---

## 6. Testing

- **Unit tests del core** (sin red, fixtures SDL inline con `buildSchema`): `npx vitest run packages/graphql-orm-core/test` — **36 tests** en 5 specs que cubren el checklist de la spec:
  - `collection-shape.spec.ts`: las tres formas de colección (relay/page/flat) y **tipos de retorno distintos por operación** (grupos de serialización: `BookItem` vs `BookConnection`/`BookCollection`, `payloadEntityField`).
  - `document-factory.spec.ts`: item/collection/create/update/delete/custom + throw sobre operación inexistente.
  - `schema-validator.spec.ts`: campos requeridos, enums, validación **recursiva** de `input-object` anidados, guard de ciclos, y rama IRI (solo con descriptor `kind: 'object'`; scalars `String`/`ID` pasan como texto).
  - `repository.spec.ts`: **13 tests** — relay/page/flat, `order` como lista preservando orden, `assertKnownArgs` rechaza filtros desconocidos, IDs opacos sin transformar, payloads de create/update/remove (con `result.data`), `call()` con selección custom, throw cuando la entidad no existe.
  - `graphql-transport.spec.ts`: POST a `/graphql` con body, `GraphQLApiError`, Bearer **condicional** de `authMiddleware`, merge de headers de `headersMiddleware`, y orden de ejecución de middleware.
- **Smoke test E2E** contra el backend real: `npx jiti smoke-test.ts` (requiere backend arriba en `http://localhost/graphql`). Ejecuta `findAll → create → findById → update → remove` y reporta el IRI creado/borrado.
- El resto de la suite: `npm run test:unit` (50 passed / 4 skipped, los skipped son opt-in de RAGF con `LIVE_BACKEND=1`).

---

## 7. Limitaciones y deudas conocidas (leer antes de usar en producción)

Deudas detectadas en la auditoría — **resueltas**:

1. ~~Autenticación no implementada~~ → `authMiddleware(getToken)` (Bearer condicional) + `headersMiddleware(getHeaders)`; `createGraphQLOrm({ authHeaders })` la registra automáticamente (§3.7).
2. ~~`call()` descartaba `selection`~~ → `buildCustomOperation` acepta `SelectionOptions` y `repository.call()` lo reenvía (§3.8).
3. ~~Snapshot de producción inexistente~~ → `frontend/public/schema.graphql` generado desde el backend (`php bin/console api:graphql:export`) y commitado; `SdlSnapshotSource('/schema.graphql')` ya carga (verificado).
4. ~~Validación de InputObjects anidados superficial~~ → recursión real contra el `InputObject` con guard de ciclos (§3.6).
5. ~~`create()/update()` enviaban el input original~~ → ahora envían `result.data` (parseado por Zod).
7. ~~Cobertura de tests parcial~~ → checklist completo cubierto en 36 tests unitarios (§6).
8. ~~Type-check global roto por errores pre-existentes~~ → `Fk*.vue` (blur) y `Dashboard.vue` (`lang="ts"`) reparados; `npm run type-check` pasa limpio.

Limitaciones reales restantes:

6. **`customOperations` heurística**: una query custom de ítem se describe con `describeCollectionOperation` (puede reportar un `collectionShape`). OK para uso típico, no exacto en todos los casos.

---

## 8. Convenciones

- Los paquetes viven en `packages/` y se consumen por alias (`@graphql-orm/core`, `@graphql-orm/vue`); **no** añadir deps npm de graphql-orm.
- Errores: lanzar `GraphQLApiError`/`ClientValidationError`/`GraphQLOrmError`, nunca `Error` a pelo, en el core.
- IDs siempre IRI (`/api/statuses/3`); `shortId()` solo para display.
- `order` siempre lista; filtros siempre dentro de `filters`; ambos se derivan de `op.args`.
- Los composables devuelven lo que devuelve `useQuery` (loading/error/data reactivos); no gestionar estado de red a mano.
