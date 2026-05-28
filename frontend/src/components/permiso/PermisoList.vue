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


  <q-table
    :pagination="pagination"
    :rows="items"
    :columns="columns"
    :rows-per-page-options="[30]"
    :loading="isLoading"
    :no-data-label="$t('unavail')"
    :no-results-label="$t('noresults')"
    :loading-label="$t('loading')"
    :rows-per-page-label="$t('recPerPage')"
    row-key="id"
    flat
    wrap-cells
    @request="sendRequest"
  >
    <template #body-cell-actions="{ row }">
      <ActionCell
        :actions="['show', 'update', 'delete']"
        @show="goToShowPage(row)"
        @update="goToUpdatePage(row)"
        @delete="deleteItem(row)"
      />
    </template>

    <template #body-cell-roles="{ value }">
      <td>
        <template v-if="router.hasRoute('RoleShow')">
          <router-link
            v-for="role in value"
            :to="{ name: 'RoleShow', params: { id: role } }"
            :key="role"
          >
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
        <q-btn
          v-if="pagesNumber > 2"
          :to="view['hydra:first'] ? view['hydra:first'] : { name: 'BookList' }"
          :disable="!view['hydra:previous']"
          icon="first_page"
          color="grey-8"
          round
          dense
          flat
        />

        <q-btn
          :to="
            !view['hydra:previous'] ||
            view['hydra:previous'] === view['hydra:first']
              ? { name: 'BookList' }
              : view['hydra:previous']
          "
          :disable="!view['hydra:previous']"
          icon="chevron_left"
          color="grey-8"
          round
          dense
          flat
        />

        <q-btn
          :to="view['hydra:next'] ? view['hydra:next'] : '#'"
          :disable="!view['hydra:next']"
          icon="chevron_right"
          color="grey-8"
          round
          dense
          flat
        />

        <q-btn
          v-if="pagesNumber > 2"
          :to="view['hydra:last'] ? view['hydra:last'] : '#'"
          :disable="!view['hydra:next']"
          icon="last_page"
          color="grey-8"
          round
          dense
          flat
        />
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
import { usePermisoListStore } from 'stores/permiso/list';
import { usePermisoDeleteStore } from 'stores/permiso/delete';
import { useBreadcrumb } from 'src/composables/breadcrumb';
import { useWatchErrors } from 'src/composables/errors';
import { useMercureList } from 'src/composables/mercureList';
import type { Permiso } from 'src/types/permiso';
import type { Pagination } from 'src/types/list';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const breadcrumb = useBreadcrumb();

const permisoListStore = usePermisoListStore();
const { items, totalItems, view, error, isLoading } = storeToRefs(permisoListStore);

const permisoDeleteStore = usePermisoDeleteStore();
const { deleted, mercureDeleted } = storeToRefs(permisoDeleteStore);

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
    name: 'roles',
    field: 'roles',
    label: t('permiso.roles'),
  },
  {
    name: 'parents',
    field: 'parents',
    label: t('permiso.parents'),
  },
  {
    name: 'children',
    field: 'children',
    label: t('permiso.children'),
  },
  {
    name: 'nombre',
    field: 'nombre',
    label: t('permiso.nombre'),
  },
  {
    name: 'nota',
    field: 'nota',
    label: t('permiso.nota'),
  },
  {
    name: 'label',
    field: 'label',
    label: t('permiso.label'),
  },
  {
    name: 'status',
    field: 'status',
    label: t('permiso.status'),
  },
  {
    name: 'id',
    field: 'id',
    label: t('permiso.id'),
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
  await permisoListStore.getItems(page.value, {  });
}

useMercureList({ store: permisoListStore, deleteStore: permisoDeleteStore });

await sendRequest();

pagination.rowsPerPage = items.value.length;
pagination.rowsNumber = totalItems.value;


function goToCreatePage() {
  router.push({ name: 'PermisoCreate' });
}

function goToShowPage(item: Permiso) {
  router.push({
    name: 'PermisoShow',
    params: { id: item['@id'] },
  });
}

function goToUpdatePage(item: Permiso) {
  router.push({
    name: 'PermisoUpdate',
    params: { id: item['@id'] },
  });
}

async function deleteItem(item: Permiso) {
  await permisoDeleteStore.deleteItem(item);

  sendRequest();
}

useWatchErrors([error]);

onBeforeUnmount(() => {
  permisoDeleteStore.$reset();
});
</script>
