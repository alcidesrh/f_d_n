import { inject, computed, type MaybeRefOrGetter, toValue } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { GRAPHQL_ORM_KEY } from '../plugin';

function useOrm() {
  const ctx = inject(GRAPHQL_ORM_KEY);
  if (!ctx) throw new Error('GraphQLOrm no está instalado — llama a app.use(createGraphQLOrm(...)) en main.ts.');
  return ctx;
}

export function useItem<T = Record<string, unknown>>(
  entity: string,
  id: MaybeRefOrGetter<string | null | undefined>,
) {
  const ctx = useOrm();
  return useQuery({
    queryKey: computed(() => ['graphql-orm', entity, 'item', toValue(id)]),
    queryFn: () => ctx.repository<T>(entity).findById(toValue(id)!),
    enabled: computed(() => ctx.isReady.value && !!toValue(id)),
  });
}
