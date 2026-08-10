import { computed, type MaybeRefOrGetter, toValue } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { useOrm } from './use-orm';
import { ormKeys } from './graphql-orm-keys';

export function useItem<T = Record<string, unknown>>(
  entity: string,
  id: MaybeRefOrGetter<string | null | undefined>,
) {
  const ctx = useOrm();
  return useQuery({
    queryKey: computed(() => ormKeys(entity).item(toValue(id))),
    queryFn: () => ctx.repository<T>(entity).findById(toValue(id)!),
    enabled: computed(() => ctx.isReady.value && !!toValue(id)),
  });
}
