import { computed, type MaybeRefOrGetter, toValue } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { useOrm } from './use-orm';
import { ormKeys } from './graphql-orm-keys';
import type { FindAllParams, SelectionOptions } from '../features/graphql-orm/types';

export function useCollection<T = Record<string, unknown>>(
  entity: string,
  params?: MaybeRefOrGetter<FindAllParams | undefined>,
  options?: MaybeRefOrGetter<SelectionOptions | undefined>,
) {
  const ctx = useOrm();
  return useQuery({
    queryKey: computed(() => ormKeys(entity).collection(toValue(params), toValue(options))),
    queryFn: () => ctx.repository<T>(entity).findAll(toValue(params), toValue(options)),
    enabled: ctx.isReady,
  });
}
