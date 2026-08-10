import { useQueryClient } from '@tanstack/vue-query';
import { useOrm } from './use-orm';
import { ormKeys } from './graphql-orm-keys';

export function useEntityMutations<T = Record<string, unknown>>(entity: string) {
  const ctx = useOrm();
  const qc = useQueryClient();
  const invalidate = () => qc.invalidateQueries({ queryKey: ormKeys(entity).all });

  const run = <R>(op: () => Promise<R>) =>
    op().then((res) => {
      invalidate();
      return res;
    });

  return {
    create: (input: Record<string, unknown>) => run(() => ctx.repository<T>(entity).create(input)),
    update: (id: string, input: Record<string, unknown>) => run(() => ctx.repository<T>(entity).update(id, input)),
    remove: (id: string) => run(() => ctx.repository<T>(entity).remove(id)),
  };
}
