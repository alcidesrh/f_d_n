import { inject } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { GRAPHQL_ORM_KEY } from '../plugin';

function useOrm() {
  const ctx = inject(GRAPHQL_ORM_KEY);
  if (!ctx) throw new Error('GraphQLOrm no está instalado — llama a app.use(createGraphQLOrm(...)) en main.ts.');
  return ctx;
}

export function useEntityMutations<T = Record<string, unknown>>(entity: string) {
  const ctx = useOrm();
  const qc = useQueryClient();
  const invalidate = () => qc.invalidateQueries({ queryKey: ['graphql-orm', entity] });

  return {
    create: (input: Record<string, unknown>) =>
      ctx
        .repository<T>(entity)
        .create(input)
        .then((res) => {
          invalidate();
          return res;
        }),

    update: (id: string, input: Record<string, unknown>) =>
      ctx
        .repository<T>(entity)
        .update(id, input)
        .then((res) => {
          invalidate();
          return res;
        }),

    remove: (id: string) =>
      ctx
        .repository<T>(entity)
        .remove(id)
        .then((res) => {
          invalidate();
          return res;
        }),
  };
}
