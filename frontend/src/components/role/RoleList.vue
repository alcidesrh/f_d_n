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
    <!-- <template #body-cell-actions="{ value }">
      <td>
        <template v-if="router.hasRoute('ActionShow')">
          <router-link
            v-for="action in value"
            :to="{ name: 'ActionShow', params: { id: action } }"
            :key="action"
          >
            {{ action }}

            <br />
          </router-link>
        </template>

        <template v-else>
          <p v-for="action in value" :key="action">
            {{ action }}
          </p>
        </template>
      </td>
    </template> -->

    <template #pagination="{ pagesNumber }">
      <template v-if="view">
        <q-btn v-if="pagesNumber > 2" :to="view['hydra:first'] ? view['hydra:first'] : { name: 'BookList' }"
          :disable="!view['hydra:previous']" icon="first_page" color="grey-8" round dense flat />

        <q-btn :to="!view['hydra:previous'] ||
            view['hydra:previous'] === view['hydra:first']
            ? { name: 'BookList' }
            : view['hydra:previous']
          " :disable="!view['hydra:previous']" icon="chevron_left" color="grey-8" round dense flat />

        <q-btn :to="view['hydra:next'] ? view['hydra:next'] : '#'" :disable="!view['hydra:next']" icon="chevron_right"
          color="grey-8" round dense flat />

        <q-btn v-if="pagesNumber > 2" :to="view['hydra:last'] ? view['hydra:last'] : '#'" :disable="!view['hydra:next']"
          icon="last_page" color="grey-8" round dense flat />
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
import { useRoleListStore } from 'stores/role/list';
import { useRoleDeleteStore } from 'stores/role/delete';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureList } from 'src/composables/mercureList';
import type { Role } from 'src/types/role';
import type { Pagination } from 'src/types/list';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();

const roleListStore = useRoleListStore();
const { items, totalItems, view, error, isLoading } = storeToRefs(roleListStore);

const roleDeleteStore = useRoleDeleteStore();
const { deleted, mercureDeleted } = storeToRefs(roleDeleteStore);

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
    name: 'nombre',
    field: 'nombre',
    label: t('role.nombre'),
  },
  {
    name: 'parents',
    field: 'parents',
    label: t('role.parents'),
  },
  {
    name: 'children',
    field: 'children',
    label: t('role.children'),
  },
  {
    name: 'permisos',
    field: 'permisos',
    label: t('role.permisos'),
  },
  {
    name: 'actions',
    field: 'actions',
    label: t('role.actions'),
  },
  {
    name: 'label',
    field: 'label',
    label: t('role.label'),
  },
  {
    name: 'id',
    field: 'id',
    label: t('role.id'),
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
  await roleListStore.getItems(page.value, {});
}

useMercureList({ store: roleListStore, deleteStore: roleDeleteStore });

await sendRequest();

pagination.rowsPerPage = items.value.length;
pagination.rowsNumber = totalItems.value;


function goToCreatePage() {
  router.push({ name: 'RoleCreate' });
}

function goToShowPage(item: Role) {
  router.push({
    name: 'RoleShow',
    params: { id: item['@id'] },
  });
}

function goToUpdatePage(item: Role) {
  router.push({
    name: 'RoleUpdate',
    params: { id: item['@id'] },
  });
}

async function deleteItem(item: Role) {
  await roleDeleteStore.deleteItem(item);

  sendRequest();
}

useWatchErrors([error]);

onBeforeUnmount(() => {
  roleDeleteStore.$reset();
});
</script>
