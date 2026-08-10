import type { FindAllParams, SelectionOptions } from '../features/graphql-orm/types';

export function ormKeys(entity: string) {
  return {
    all: ['graphql-orm', entity] as const,
    collection(params?: FindAllParams, options?: SelectionOptions) {
      return ['graphql-orm', entity, 'collection', params, options] as const;
    },
    item(id: string | null | undefined) {
      return ['graphql-orm', entity, 'item', id] as const;
    },
  };
}
