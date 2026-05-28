<template>
  <Toolbar :actions="['add']" @add="goToCreatePage">
    <template #left>
      <Breadcrumb :values="breadcrumb" />
    </template>
  </Toolbar>

  <q-banner v-if="deleted" class="bg-positive text-white q-ma-md">
    {{ deleted['@id'] }} deleted.
  </q-banner>
  <q-banner v-if="mercureDeleted" class="bg-positive text-white q-ma-md">
    {{ mercureDeleted['@id'] }} deleted by another user.
  </q-banner>


  <q-table :pagination="pagination" :rows="items" :columns="columns" :rows-per-page-options="[30]" :loading="isLoading"
    :no-data-label="$t('unavail')" :no-results-label="$t('noresults')" :loading-label="$t('loading')"
    :rows-per-page-label="$t('recPerPage')" row-key="id" flat wrap-cells @request="sendRequest">
    <template #body-cell-actions="{ row }">
      <ActionCell :actions="['show', 'update', 'delete']" @show="goToShowPage(row)" @update="goToUpdatePage(row)"
        @delete="deleteItem(row)" />
    </template>

    <template #body-cell-permisos="{ value }">
      <td>
        <template v-if="router.hasRoute('PermisoShow')">
          <router-link v-for="permiso in value" :to="{ name: 'PermisoShow', params: { id: permiso } }" :key="permiso">
            {{ permiso }}

            <br />
          </router-link>
        </template>

        <template v-else>
          <p v-for="permiso in value" :key="permiso">
            {{ permiso }}
          </p>
        </template>
      </td>
    </template>
    <template #body-cell-localidad="{ value }">
      <td>
        <router-link v-if="router.hasRoute('LocalidadShow')" :to="{ name: 'LocalidadShow', params: { id: value } }">
          {{ value }}
        </router-link>

        <p v-else>
          {{ value }}
        </p>
      </td>
    </template>
    <template #body-cell-roles="{ value }">
      <td>
        <template v-if="router.hasRoute('RoleShow')">
          <router-link v-for="role in value" :to="{ name: 'RoleShow', params: { id: role } }" :key="role">
            {{ role }}

            <br />
          </router-link>
        </template>

        <template v-else>
          <p v-for="role in value" :key="role">
            {{ role }}
          </p>
        </template>
      </td>
    </template>

    <template #pagination="{ pagesNumber }">
      <template v-if="view">
        <q-btn v-if="pagesNumber > 2" :to="view['first'] ? view['first'] : { name: 'BookList' }"
          :disable="!view['previous']" icon="first_page" color="grey-8" round dense flat />

        <q-btn :to="!view['previous'] ||
            view['previous'] === view['first']
            ? { name: 'BookList' }
            : view['previous']
          " :disable="!view['previous']" icon="chevron_left" color="grey-8" round dense flat />

        <q-btn :to="view['next'] ? view['next'] : '#'" :disable="!view['next']" icon="chevron_right" color="grey-8"
          round dense flat />

        <q-btn v-if="pagesNumber > 2" :to="view['last'] ? view['last'] : '#'" :disable="!view['next']" icon="last_page"
          color="grey-8" round dense flat />
      </template>
    </template>
  </q-table>
</template>

<script lang="ts" setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useI18n } from 'vue-i18n';
import Toolbar from 'components/common/CommonToolbar.vue';
import Breadcrumb from 'components/common/CommonBreadcrumb.vue';
import ActionCell from 'components/common/CommonActionCell.vue';
import { formatDateTime } from 'src/utils/autoimport/date';
import { useUserListStore } from 'stores/user/list';
import { useUserDeleteStore } from 'stores/user/delete';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureList } from 'src/composables/mercureList';
import type { User } from 'src/types/user';
import type { Pagination } from 'src/types/list';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();

const userListStore = useUserListStore();
const { items, totalItems, view, error, isLoading } = storeToRefs(userListStore);

const userDeleteStore = useUserDeleteStore();
const { deleted, mercureDeleted } = storeToRefs(userDeleteStore);

const page = ref('1');
const pagination: Pagination = {
  sortBy: undefined,
  descending: false,
  page: 1,
  rowsPerPage: 3,
  rowsNumber: 1,
};
const columns = [
  { name: 'actions', label: t('actions'), field: '' },
  { name: 'id', field: '@id', label: t('id') },
  {
    name: 'username',
    field: 'username',
    label: t('user.username'),
  },
  {
    name: 'password',
    field: 'password',
    label: t('user.password'),
  },
  {
    name: 'plainPassword',
    field: 'plainPassword',
    label: t('user.plainPassword'),
  },
  {
    name: 'apiTokens',
    field: 'apiTokens',
    label: t('user.apiTokens'),
  },
  {
    name: 'userRoles',
    field: 'userRoles',
    label: t('user.userRoles'),
  },
  {
    name: 'permisos',
    field: 'permisos',
    label: t('user.permisos'),
  },
  {
    name: 'label',
    field: 'label',
    label: t('user.label'),
  },
  {
    name: 'createdAt',
    field: 'createdAt',
    label: t('user.createdAt'),
    format: (value: string) => {
      return formatDateTime(value);
    },
  },
  {
    name: 'updatedAt',
    field: 'updatedAt',
    label: t('user.updatedAt'),
    format: (value: string) => {
      return formatDateTime(value);
    },
  },
  {
    name: 'status',
    field: 'status',
    label: t('user.status'),
  },
  {
    name: 'legacyId',
    field: 'legacyId',
    label: t('user.legacyId'),
  },
  {
    name: 'apellido',
    field: 'apellido',
    label: t('user.apellido'),
  },
  {
    name: 'nombre',
    field: 'nombre',
    label: t('user.nombre'),
  },
  {
    name: 'email',
    field: 'email',
    label: t('user.email'),
  },
  {
    name: 'nit',
    field: 'nit',
    label: t('user.nit'),
  },
  {
    name: 'telefono',
    field: 'telefono',
    label: t('user.telefono'),
  },
  {
    name: 'direccion',
    field: 'direccion',
    label: t('user.direccion'),
  },
  {
    name: 'localidad',
    field: 'localidad',
    label: t('user.localidad'),
  },
  {
    name: 'fullName',
    field: 'fullName',
    label: t('user.fullName'),
  },
  {
    name: 'id',
    field: 'id',
    label: t('user.id'),
  },
  {
    name: 'userIdentifier',
    field: 'userIdentifier',
    label: t('user.userIdentifier'),
  },
  {
    name: 'roles',
    field: 'roles',
    label: t('user.roles'),
  },
  {
    name: 'token',
    field: 'token',
    label: t('user.token'),
  },
  {
    name: 'validTokenStrings',
    field: 'validTokenStrings',
    label: t('user.validTokenStrings'),
  },
];

watch(
  () => route.query.page,
  (newPage) => {
    page.value = newPage as string;
    sendRequest();
  },
  { immediate: true }
);

async function sendRequest() {
  await userListStore.getItems(page.value, {});
}

useMercureList({ store: userListStore, deleteStore: userDeleteStore });

await sendRequest();

pagination.rowsPerPage = items.value.length;
pagination.rowsNumber = totalItems.value;


function goToCreatePage() {
  router.push({ name: 'UserCreate' });
}

function goToShowPage(item: User) {
  router.push({
    name: 'UserShow',
    params: { id: item['@id'] },
  });
}

function goToUpdatePage(item: User) {
  router.push({
    name: 'UserUpdate',
    params: { id: item['@id'] },
  });
}

async function deleteItem(item: User) {
  await userDeleteStore.deleteItem(item);

  sendRequest();
}

useWatchErrors([error]);

onBeforeUnmount(() => {
  userDeleteStore.$reset();
});
</script>
