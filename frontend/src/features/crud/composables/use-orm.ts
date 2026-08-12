import { inject } from 'vue';
import { GRAPHQL_ORM_KEY } from '../../graphql-orm/vue/src/plugin';
import type { GraphQLOrmContext } from '../../graphql-orm/types';

export function useOrm(): GraphQLOrmContext {
  const ctx = inject(GRAPHQL_ORM_KEY);
  if (!ctx) throw new Error('GraphQLOrm no está instalado — llama a app.use(createGraphQLOrm(...)) en main.ts.');
  return ctx;
}
