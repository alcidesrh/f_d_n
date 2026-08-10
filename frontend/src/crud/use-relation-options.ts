import { inject, ref, type Ref } from 'vue';
import { GRAPHQL_ORM_KEY, type GraphQLOrmContext } from '@graphql-orm/vue';
import { buildEntityDescriptor, type FindAllParams } from '@graphql-orm/core';
import type { GraphQLSchema } from 'graphql';
import { displayName } from './relation-display';

export interface RelationOption {
  label: string;
  value: string | number | boolean;
}

interface OptionsEntry {
  options: Ref<RelationOption[]>;
  loading: Ref<boolean>;
}

/** Caché de opciones por tipo de relación (compartida entre listado y formulario). */
const cache = new Map<string, OptionsEntry>();

function paramsForShape(schema: GraphQLSchema | null, typeName: string): FindAllParams | undefined {
  if (!schema) return undefined;
  const shape = buildEntityDescriptor(schema, typeName).queries.collection?.collectionShape;
  if (shape === 'relay') return { first: 200 };
  if (shape === 'page') return { filters: { itemsPerPage: 200 } };
  return undefined;
}

async function loadOptions(ctx: GraphQLOrmContext, typeName: string, entry: OptionsEntry) {
  try {
    const res = await ctx.repository(typeName).findAll(paramsForShape(ctx.schema.value, typeName));
    entry.options.value = res.items
      .map((i) => ({ label: displayName(i), value: String(i.id) }))
      .filter((o) => o.value !== '' && o.label !== '' && o.label !== '—');
  } catch {
    entry.options.value = [];
  } finally {
    entry.loading.value = false;
  }
}

export function useRelationOptions() {
  const injected = inject(GRAPHQL_ORM_KEY);
  if (!injected) throw new Error('GraphQLOrm no está instalado — llama a app.use(createGraphQLOrm(...)) en main.ts.');
  const ctx: GraphQLOrmContext = injected;

  function optionsFor(typeName: string): Ref<RelationOption[]> {
    let entry = cache.get(typeName);
    if (!entry) {
      entry = { options: ref([]), loading: ref(true) };
      cache.set(typeName, entry);
      void loadOptions(ctx, typeName, entry);
    }
    return entry.options;
  }

  return { optionsFor };
}
