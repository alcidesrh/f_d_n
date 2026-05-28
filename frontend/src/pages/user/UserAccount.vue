<template>
  <CrudForm
    :class="{ 'opacity-70': gloading }"
    :data="data"
    :store="store"
    :identifier="$route.params.id ? { username: $route.params.id } : null"
  >
    <template #left-header="{ user }">
      <span class="surfacecontrast-600 font-semibold u-text-2">Mi cuenta</span>
    </template>
    <template #CrudButton>
      <Button
        type="submit"
        severity="primary"
        label="Guardar"
        icon="pi pi-save"
        :loading="!!gloading"
      />
    </template>

    <template #localidad="{ schema }">
      <FormKitSchema :schema="schema" :data="data" />
    </template>
    <template #roles="{ schema }">
      <FormKitSchema :schema="schema" :data="data" />
    </template>
  </CrudForm>
</template>

<script setup lang="ts">
import * as gqlBuilder from "gql-query-builder";
import gql from "graphql-tag";

const store = useUserStore();
const api = useMetadataStore();
const { entity } = storeToRefs(store);

const localidadStore = useLocalidadStore();
const { items } = storeToRefs(localidadStore);
localidadStore.getItems();

const roleStore = useRoleStore();
const { items: roles } = storeToRefs(roleStore);
roleStore.getItems();

const permisoStore = usePermisoStore();
const { items: permisos } = storeToRefs(permisoStore);
permisoStore.getItems();
const data = ref();
onBeforeMount(() => {
  data.value = {
    localidades: items,
    roles: computed(() => roles.value),
    permisos: computed(() => permisos.value),
    // item: computed(() => entity.value.item),
    submit: (data) => store.submit(data),
  };
});
</script>
