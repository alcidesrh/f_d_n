import { Entity } from "@/types/graphql";

export const entities: Ref<Record<string, Entity>> = ref({});
export const types: Ref<Record<string, Entity>> = ref({});
// export let queries = 90;
// export const mutations = ref({});
// export const payloads = ref({});

export function useEntity(name) {
  return entities.value[name];
}
