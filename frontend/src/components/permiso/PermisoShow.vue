<template>
  <Toolbar :actions="['delete']" @delete="deleteItem">
    <template #left>
      <Breadcrumb :values="breadcrumb" />
    </template>
  </Toolbar>

  <div v-if="item" class="table-responsive">
    <q-markup-table>
      <thead>
        <tr>
          <th>{{ $t('field') }}</th>
          <th>{{ $t('value') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ $t('permiso.roles') }}</td>

          <td>
            <template v-if="router.hasRoute('RoleShow')">
              <router-link
                v-for="role in item.roles"
                :to="{ name: 'RoleShow', params: { id: role } }"
                :key="role"
              >
                {{ role }}

                <br />
              </router-link>
            </template>

            <template v-else>
              <p
                v-for="role in item.roles"
                :key="role"
              >
                {{ role }}
              </p>
            </template>
          </td>
        </tr>
        <tr>
          <td>{{ $t('permiso.parents') }}</td>

          <td>
            {{ item.parents }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('permiso.children') }}</td>

          <td>
            {{ item.children }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('permiso.nombre') }}</td>

          <td>
            {{ item.nombre }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('permiso.nota') }}</td>

          <td>
            {{ item.nota }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('permiso.label') }}</td>

          <td>
            {{ item.label }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('permiso.status') }}</td>

          <td>
            {{ item.status }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('permiso.id') }}</td>

          <td>
            {{ item.id }}
                      </td>
        </tr>
      </tbody>
    </q-markup-table>
  </div>

  <Loading :showing="isLoading" />
</template>

<script lang="ts" setup>
import { onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useI18n } from 'vue-i18n';
import Toolbar from 'components/common/CommonToolbar.vue';
import Breadcrumb from 'components/common/CommonBreadcrumb.vue';
import Loading from 'components/common/CommonLoading.vue';
import { usePermisoShowStore } from 'stores/permiso/show';
import { usePermisoDeleteStore } from 'stores/permiso/delete';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureItem } from 'src/composables/mercureItem';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();

const permisoShowStore = usePermisoShowStore();
const { retrieved: item, isLoading, error } = storeToRefs(permisoShowStore);

const permisoDeleteStore = usePermisoDeleteStore();
const { deleted, error: deleteError } = storeToRefs(permisoDeleteStore);

useMercureItem({
  store: permisoShowStore,
  deleteStore: permisoDeleteStore,
  redirectRouteName: 'PermisoList',
});

await permisoShowStore.retrieve(decodeURIComponent(route.params.id as string));

async function deleteItem() {
  if (!item?.value) {
    permisoDeleteStore.setError(t('This item does not exist anymore'));
    return;
  }

  await permisoDeleteStore.deleteItem(item.value);

  if (!deleted?.value) {
    return;
  }

  router.push({ name: 'PermisoList' });
}

useWatchErrors([error, deleteError]);

onBeforeUnmount(() => {
  permisoShowStore.$reset();
});
</script>
