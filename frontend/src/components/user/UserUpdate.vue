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
import Form from 'components/user/UserForm.vue';
import { useUserUpdateStore } from 'stores/user/update';
import { useUserDeleteStore } from 'stores/user/delete';
import { useNotifications } from 'src/composables/notifications';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureItem } from 'src/composables/mercureItem';
import type { User } from 'src/types/user';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();
const { displaySuccessNotification } = useNotifications();

const userUpdateStore = useUserUpdateStore();
const {
  retrieved: item,
  updated,
  isLoading,
  error,
  violations,
} = storeToRefs(userUpdateStore);

const userDeleteStore = useUserDeleteStore();
const { isLoading: deleteLoading, error: deleteError } =
  storeToRefs(userDeleteStore);

useMercureItem({
  store: userUpdateStore,
  deleteStore: userDeleteStore,
  redirectRouteName: 'UserList',
});

await userUpdateStore.retrieve(decodeURIComponent(route.params.id as string));

async function deleteItem() {
  if (!item?.value) {
    userUpdateStore.setError('No user found. Please reload');
    return;
  }

  await userDeleteStore.deleteItem(item?.value);

  router.push({ name: 'UserList' });
}

async function update(item: User) {
  await userUpdateStore.update(item);

  if (!updated?.value) {
    return;
  }

  displaySuccessNotification(`${item['@id']} ${t('updated')}.`);
}

useWatchErrors([error, deleteError]);

onBeforeUnmount(() => {
  userUpdateStore.$reset();
  userDeleteStore.$reset();
});
</script>
