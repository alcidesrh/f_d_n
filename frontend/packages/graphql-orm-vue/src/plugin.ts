import type { App, InjectionKey, Ref } from 'vue';
import { ref, shallowRef } from 'vue';
import { SchemaRegistry } from '../../graphql-orm-core/src/schema/schema-registry';
import { FetchTransport, headersMiddleware } from '../../graphql-orm-core/src/transport/graphql-transport';
import { createRepository, type Repository } from '../../graphql-orm-core/src/repository/create-repository';
import type { IntrospectionSource } from '../../graphql-orm-core/src/introspection/introspection-source';

export interface GraphQLOrmOptions {
  endpoint: string;
  source: IntrospectionSource;
  entities: string[];
  authHeaders?: () => Record<string, string> | Promise<Record<string, string>>;
}

export interface GraphQLOrmContext {
  isReady: Ref<boolean>;
  error: Ref<unknown>;
  repository<T = Record<string, unknown>>(typeName: string): Repository<T>;
}

export const GRAPHQL_ORM_KEY: InjectionKey<GraphQLOrmContext> = Symbol('graphql-orm');

export function createGraphQLOrm(options: GraphQLOrmOptions) {
  const isReady = ref(false);
  const error = ref<unknown>(null);
  const registryRef = shallowRef<SchemaRegistry | null>(null);
  const transport = new FetchTransport(options.endpoint);
  if (options.authHeaders) {
    transport.use(headersMiddleware(options.authHeaders));
  }
  const repoCache = new Map<string, Repository<any>>();

  const context: GraphQLOrmContext = {
    isReady,
    error,
    repository<T>(typeName: string) {
      if (!registryRef.value) {
        throw new Error('GraphQLOrm todavía no está listo — espera "isReady" o "readyPromise" antes de consultar el repositorio.');
      }
      if (!repoCache.has(typeName)) {
        repoCache.set(typeName, createRepository<T>(registryRef.value, transport, typeName));
      }
      return repoCache.get(typeName)!;
    },
  };

  const readyPromise = options.source
    .load()
    .then((schema) => {
      const registry = new SchemaRegistry(schema);
      registry.warmUp(options.entities);
      registryRef.value = registry;
      isReady.value = true;
    })
    .catch((e) => {
      error.value = e;
      throw e;
    });

  return {
    install(app: App) {
      app.provide(GRAPHQL_ORM_KEY, context);
    },
    readyPromise,
  };
}
