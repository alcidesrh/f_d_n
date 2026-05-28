<template>
  <Toolbar>
    <template #left>
      <Breadcrumb :values="breadcrumb" />
    </template>
  </Toolbar>

  <Form :errors="violations" @submit="create" />

  <Loading :showing="isLoading" />
</template>

<script lang="ts" setup>
import { onBeforeUnmount } from "vue";
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import Toolbar from 'components/common/CommonToolbar.vue';
import Breadcrumb from 'components/common/CommonBreadcrumb.vue';
import Loading from 'components/common/CommonLoading.vue';
import Form from 'components/permiso/PermisoForm.vue';
import { usePermisoCreateStore } from 'stores/permiso/create';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import type { Permiso } from 'src/types/permiso';

const router = useRouter();
const breadcrumb = useBreadcrumb();

const permisoCreateStore = usePermisoCreateStore();
const { created, isLoading, violations, error } = storeToRefs(permisoCreateStore);

async function create(item: Permiso) {
  await permisoCreateStore.create(item);

  if (!created?.value) {
    return;
  }

  router.push({ name: 'PermisoUpdate', params: { id: created?.value?.['@id'] } });
}

useWatchErrors([error]);

onBeforeUnmount(() => {
  permisoCreateStore.$reset();
});
</script>
