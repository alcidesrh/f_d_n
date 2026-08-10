import { inject } from 'vue';
import { GRAPHQL_ORM_KEY } from '../features/graphql-orm/vue/src/plugin';
import type { GraphQLOrmContext } from '../features/graphql-orm/types';

export function useOrm(): GraphQLOrmContext {
  const ctx = inject(GRAPHQL_ORM_KEY);
  if (!ctx) throw new Error('GraphQLOrm no está instalado — llama a app.use(createGraphQLOrm(...)) en main.ts.');
  return ctx;
}
