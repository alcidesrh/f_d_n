import { inject, computed, type MaybeRefOrGetter, toValue } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { GRAPHQL_ORM_KEY } from '../plugin';
import type { FindAllParams } from '../../../graphql-orm-core/src/repository/create-repository';
import type { SelectionOptions } from '../../../graphql-orm-core/src/documents/selection-set-builder';

function useOrm() {
  const ctx = inject(GRAPHQL_ORM_KEY);
  if (!ctx) throw new Error('GraphQLOrm no está instalado — llama a app.use(createGraphQLOrm(...)) en main.ts.');
  return ctx;
}

export function useCollection<T = Record<string, unknown>>(
  entity: string,
  params?: MaybeRefOrGetter<FindAllParams | undefined>,
  options?: MaybeRefOrGetter<SelectionOptions | undefined>,
) {
  const ctx = useOrm();
  return useQuery({
    queryKey: computed(() => ['graphql-orm', entity, 'collection', toValue(params), toValue(options)]),
    queryFn: () => ctx.repository<T>(entity).findAll(toValue(params), toValue(options)),
    enabled: ctx.isReady,
  });
}
