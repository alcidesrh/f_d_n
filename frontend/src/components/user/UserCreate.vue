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
import Form from 'components/user/UserForm.vue';
import { useUserCreateStore } from 'stores/user/create';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import type { User } from 'src/types/user';

const router = useRouter();
const breadcrumb = useBreadcrumb();

const userCreateStore = useUserCreateStore();
const { created, isLoading, violations, error } = storeToRefs(userCreateStore);

async function create(item: User) {
  await userCreateStore.create(item);

  if (!created?.value) {
    return;
  }

  router.push({ name: 'UserUpdate', params: { id: created?.value?.['@id'] } });
}

useWatchErrors([error]);

onBeforeUnmount(() => {
  userCreateStore.$reset();
});
</script>
