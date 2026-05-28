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
import Form from 'components/role/RoleForm.vue';
import { useRoleCreateStore } from 'stores/role/create';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import type { Role } from 'src/types/role';

const router = useRouter();
const breadcrumb = useBreadcrumb();

const roleCreateStore = useRoleCreateStore();
const { created, isLoading, violations, error } = storeToRefs(roleCreateStore);

async function create(item: Role) {
  await roleCreateStore.create(item);

  if (!created?.value) {
    return;
  }

  router.push({ name: 'RoleUpdate', params: { id: created?.value?.['@id'] } });
}

useWatchErrors([error]);

onBeforeUnmount(() => {
  roleCreateStore.$reset();
});
</script>
