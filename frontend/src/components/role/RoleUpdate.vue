<template>
  <div>
    <Toolbar :actions="['delete']" @delete="deleteItem">
      <template #left>
        <Breadcrumb :values="breadcrumb" :item="item" />
      </template>
    </Toolbar>

    <Form v-if="item" :values="item" :errors="violations" @submit="update" />

    <Loading :showing="isLoading || deleteLoading" />
  </div>
</template>

<script lang="ts" setup>
import { onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useI18n } from 'vue-i18n';
import Toolbar from 'components/common/CommonToolbar.vue';
import Breadcrumb from 'components/common/CommonBreadcrumb.vue';
import Loading from 'components/common/CommonLoading.vue';
import Form from 'components/role/RoleForm.vue';
import { useRoleUpdateStore } from 'stores/role/update';
import { useRoleDeleteStore } from 'stores/role/delete';
import { useNotifications } from 'src/composables/notifications';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureItem } from 'src/composables/mercureItem';
import type { Role } from 'src/types/role';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();
const { displaySuccessNotification } = useNotifications();

const roleUpdateStore = useRoleUpdateStore();
const {
  retrieved: item,
  updated,
  isLoading,
  error,
  violations,
} = storeToRefs(roleUpdateStore);

const roleDeleteStore = useRoleDeleteStore();
const { isLoading: deleteLoading, error: deleteError } =
  storeToRefs(roleDeleteStore);

useMercureItem({
  store: roleUpdateStore,
  deleteStore: roleDeleteStore,
  redirectRouteName: 'RoleList',
});

await roleUpdateStore.retrieve(decodeURIComponent(route.params.id as string));

async function deleteItem() {
  if (!item?.value) {
    roleUpdateStore.setError('No role found. Please reload');
    return;
  }

  await roleDeleteStore.deleteItem(item?.value);

  router.push({ name: 'RoleList' });
}

async function update(item: Role) {
  await roleUpdateStore.update(item);

  if (!updated?.value) {
    return;
  }

  displaySuccessNotification(`${item['@id']} ${t('updated')}.`);
}

useWatchErrors([error, deleteError]);

onBeforeUnmount(() => {
  roleUpdateStore.$reset();
  roleDeleteStore.$reset();
});
</script>
