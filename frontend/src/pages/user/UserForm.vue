<template>
  <div w-full>
    <CrudForm
      :data="data"
      :store="store"
      :identifier="$route.params.id ? { username: $route.params.id } : null"
    >
      <!-- <template #localidad="{ schema }">
        <FormKitSchema :schema="schema" :data="data" />
      </template>
      <template #roles="{ schema }">
        <FormKitSchema :schema="schema" :data="data" />
      </template> -->
    </CrudForm>
  </div>
</template>
<script setup lang="ts">
import { useRoleStore } from "@/stores/role/roleStore";
const store = useUserStore();
const { schema, entity } = storeToRefs(store);

const localidadStore = useLocalidadStore();
const { items } = storeToRefs(localidadStore);
localidadStore.getItems();

const roleStore = useRoleStore();
const { items: roles } = storeToRefs(roleStore);
roleStore.getItems();

const permisoStore = usePermisoStore();
const { items: permisos } = storeToRefs(permisoStore);
permisoStore.getItems();

const data = ref({
  localidades: items,
  roles,
  permisos,
  item: computed(() => entity.value.item),
  // submit: (data) => store.submit(data),
});

function submit() {}
</script>
