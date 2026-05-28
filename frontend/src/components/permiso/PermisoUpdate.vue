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
import Form from 'components/permiso/PermisoForm.vue';
import { usePermisoUpdateStore } from 'stores/permiso/update';
import { usePermisoDeleteStore } from 'stores/permiso/delete';
import { useNotifications } from 'src/composables/notifications';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureItem } from 'src/composables/mercureItem';
import type { Permiso } from 'src/types/permiso';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();
const { displaySuccessNotification } = useNotifications();

const permisoUpdateStore = usePermisoUpdateStore();
const {
  retrieved: item,
  updated,
  isLoading,
  error,
  violations,
} = storeToRefs(permisoUpdateStore);

const permisoDeleteStore = usePermisoDeleteStore();
const { isLoading: deleteLoading, error: deleteError } =
  storeToRefs(permisoDeleteStore);

useMercureItem({
  store: permisoUpdateStore,
  deleteStore: permisoDeleteStore,
  redirectRouteName: 'PermisoList',
});

await permisoUpdateStore.retrieve(decodeURIComponent(route.params.id as string));

async function deleteItem() {
  if (!item?.value) {
    permisoUpdateStore.setError('No permiso found. Please reload');
    return;
  }

  await permisoDeleteStore.deleteItem(item?.value);

  router.push({ name: 'PermisoList' });
}

async function update(item: Permiso) {
  await permisoUpdateStore.update(item);

  if (!updated?.value) {
    return;
  }

  displaySuccessNotification(`${item['@id']} ${t('updated')}.`);
}

useWatchErrors([error, deleteError]);

onBeforeUnmount(() => {
  permisoUpdateStore.$reset();
  permisoDeleteStore.$reset();
});
</script>
