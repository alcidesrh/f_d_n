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
          <td>{{ $t('user.username') }}</td>

          <td>
            {{ item.username }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.password') }}</td>

          <td>
            {{ item.password }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.plainPassword') }}</td>

          <td>
            {{ item.plainPassword }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.apiTokens') }}</td>

          <td>
            {{ item.apiTokens }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.userRoles') }}</td>

          <td>
            {{ item.userRoles }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.permisos') }}</td>

          <td>
            <template v-if="router.hasRoute('PermisoShow')">
              <router-link v-for="permiso in item.permisos" :to="{ name: 'PermisoShow', params: { id: permiso } }"
                :key="permiso">
                {{ permiso }}

                <br />
              </router-link>
            </template>

            <template v-else>
              <p v-for="permiso in item.permisos" :key="permiso">
                {{ permiso }}
              </p>
            </template>
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.label') }}</td>

          <td>
            {{ item.label }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.createdAt') }}</td>

          <td>
            {{ formatDateTime(item.createdAt) }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.updatedAt') }}</td>

          <td>
            {{ formatDateTime(item.updatedAt) }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.status') }}</td>

          <td>
            {{ item.status }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.legacyId') }}</td>

          <td>
            {{ item.legacyId }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.apellido') }}</td>

          <td>
            {{ item.apellido }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.nombre') }}</td>

          <td>
            {{ item.nombre }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.email') }}</td>

          <td>
            {{ item.email }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.nit') }}</td>

          <td>
            {{ item.nit }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.telefono') }}</td>

          <td>
            {{ item.telefono }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.direccion') }}</td>

          <td>
            {{ item.direccion }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.localidad') }}</td>

          <td>
            <router-link v-if="router.hasRoute('LocalidadShow')"
              :to="{ name: 'LocalidadShow', params: { id: item.localidad } }">
              {{ item.localidad }}
            </router-link>

            <p v-else>
              {{ item.localidad }}
            </p>
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.fullName') }}</td>

          <td>
            {{ item.fullName }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.id') }}</td>

          <td>
            {{ item.id }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.userIdentifier') }}</td>

          <td>
            {{ item.userIdentifier }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.roles') }}</td>

          <td>
            <template v-if="router.hasRoute('RoleShow')">
              <router-link v-for="role in item.roles" :to="{ name: 'RoleShow', params: { id: role } }" :key="role">
                {{ role }}

                <br />
              </router-link>
            </template>

            <template v-else>
              <p v-for="role in item.roles" :key="role">
                {{ role }}
              </p>
            </template>
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.token') }}</td>

          <td>
            {{ item.token }}
          </td>
        </tr>
        <tr>
          <td>{{ $t('user.validTokenStrings') }}</td>

          <td>
            {{ item.validTokenStrings }}
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
import { useUserShowStore } from 'stores/user/show';
import { formatDateTime } from 'src/utils/autoimport/date';
import { useUserDeleteStore } from 'stores/user/delete';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureItem } from 'src/composables/mercureItem';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();

const userShowStore = useUserShowStore();
const { retrieved: item, isLoading, error } = storeToRefs(userShowStore);

const userDeleteStore = useUserDeleteStore();
const { deleted, error: deleteError } = storeToRefs(userDeleteStore);

useMercureItem({
  store: userShowStore,
  deleteStore: userDeleteStore,
  redirectRouteName: 'UserList',
});

await userShowStore.retrieve(decodeURIComponent(route.params.id as string));

async function deleteItem() {
  if (!item?.value) {
    userDeleteStore.setError(t('This item does not exist anymore'));
    return;
  }

  await userDeleteStore.deleteItem(item.value);

  if (!deleted?.value) {
    return;
  }

  router.push({ name: 'UserList' });
}

useWatchErrors([error, deleteError]);

onBeforeUnmount(() => {
  userShowStore.$reset();
});
</script>
