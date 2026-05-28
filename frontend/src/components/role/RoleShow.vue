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
          <td>{{ $t('role.nombre') }}</td>

          <td>
            {{ item.nombre }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('role.parents') }}</td>

          <td>
            {{ item.parents }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('role.children') }}</td>

          <td>
            {{ item.children }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('role.permisos') }}</td>

          <td>
            <template v-if="router.hasRoute('PermisoShow')">
              <router-link
                v-for="permiso in item.permisos"
                :to="{ name: 'PermisoShow', params: { id: permiso } }"
                :key="permiso"
              >
                {{ permiso }}

                <br />
              </router-link>
            </template>

            <template v-else>
              <p
                v-for="permiso in item.permisos"
                :key="permiso"
              >
                {{ permiso }}
              </p>
            </template>
          </td>
        </tr>
        <tr>
          <td>{{ $t('role.actions') }}</td>

          <td>
            <template v-if="router.hasRoute('ActionShow')">
              <router-link
                v-for="action in item.actions"
                :to="{ name: 'ActionShow', params: { id: action } }"
                :key="action"
              >
                {{ action }}

                <br />
              </router-link>
            </template>

            <template v-else>
              <p
                v-for="action in item.actions"
                :key="action"
              >
                {{ action }}
              </p>
            </template>
          </td>
        </tr>
        <tr>
          <td>{{ $t('role.label') }}</td>

          <td>
            {{ item.label }}
                      </td>
        </tr>
        <tr>
          <td>{{ $t('role.id') }}</td>

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
import { useRoleShowStore } from 'stores/role/show';
import { useRoleDeleteStore } from 'stores/role/delete';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureItem } from 'src/composables/mercureItem';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();

const roleShowStore = useRoleShowStore();
const { retrieved: item, isLoading, error } = storeToRefs(roleShowStore);

const roleDeleteStore = useRoleDeleteStore();
const { deleted, error: deleteError } = storeToRefs(roleDeleteStore);

useMercureItem({
  store: roleShowStore,
  deleteStore: roleDeleteStore,
  redirectRouteName: 'RoleList',
});

await roleShowStore.retrieve(decodeURIComponent(route.params.id as string));

async function deleteItem() {
  if (!item?.value) {
    roleDeleteStore.setError(t('This item does not exist anymore'));
    return;
  }

  await roleDeleteStore.deleteItem(item.value);

  if (!deleted?.value) {
    return;
  }

  router.push({ name: 'RoleList' });
}

useWatchErrors([error, deleteError]);

onBeforeUnmount(() => {
  roleShowStore.$reset();
});
</script>
