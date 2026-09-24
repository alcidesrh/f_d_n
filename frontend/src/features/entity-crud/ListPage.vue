<template>
  <div v-if="store" class="card flex flex-col overflow-hidden" style="min-height: 400px">
    <ListToolbar
      :selection-mode="selectionMode"
      :selected-count="selection.length"
      :hidden-columns="hiddenColumns"
      @toggle-selection="toggleSelection"
      @restore="(field) => setColumnVisible(field, true)"
      @reset="resetView"
    />
    <DataTable
      v-model:selection="selection"
      :value="visibleItems"
      :loading="loading.loading"
      row-key="id"
      scrollable
      scroll-height="flex"
      reorderable-columns
      :edit-mode="canEdit ? 'cell' : undefined"
      @column-reorder="onColumnReorder"
      @cell-edit-complete="onCellEditComplete"
    >
      <Column v-for="col in visibleColumns" :key="col.field" :field="col.field">
        <template #header>
          <div class="relative">
            <div class="col-head">
              <div class="relative flex items-center justify-between gap-1">
                <span class="truncate font-semibold capitalize">{{ col.label ?? col.field }}</span>
                <span class="flex gap-3">
                  <button
                    v-if="isSortable(col)"
                    type="button"
                    class="ml-3"
                    :aria-label="`Ordenar por ${col.label ?? col.field}`"
                    :data-sort="sortDirection(store.order, col.field) ?? 'none'"
                    @click.stop="toggleSort(col.field)"
                  >
                    <icon :name="SORT_ICONS[sortDirection(store.order, col.field) ?? 'none']" />
                  </button>
                  <button
                    v-if="filterNodes.has(col.field)"
                    type="button"
                    :aria-label="`Filtrar ${col.label ?? col.field}`"
                    @click.stop="col.showFilter = !col.showFilter"
                  >
                    <icon :name="filters[col.field] ? 'filter-filled' : 'filter'" :class="{ 'text-primary': filters[col.field] }" />
                  </button>
                  <button type="button" :aria-label="`Ocultar columna ${col.label ?? col.field}`" @click="setColumnVisible(col.field, false)">
                    <icon name="square-minus" />
                  </button>
                </span>
              </div>
              <div class="column-filter-input" :class="{ 'show-filter': col.showFilter }" @click.stop>
                <FormKitSchema v-if="filterNodes.has(col.field)" :schema="[filterNodes.get(col.field)!]" />
              </div>
            </div>
            <div class="absolute bottom-0 h-[40px] w-[3px] border-r border-r-surface-200" />
          </div>
        </template>
        <template #body="{ data }">
          <ListCell :column="col" :data="data" :filter-value="highlightFor(col.field)" />
        </template>
        <template v-if="canEditCell(col)" #editor="{ data }">
          <ListCellEditor :entity="entityName" :column="col" :data="data" />
        </template>
      </Column>
      <Column
        align-frozen="right"
        frozen
        header-class="col-actions"
        body-class="col-actions"
        :exportable="false"
        :reorderable-column="false"
        :selection-mode="selectionMode ? 'multiple' : undefined"
      >
        <template v-if="!selectionMode" #body="{ data }">
          <ListActions :item="data" @edit="onEdit" @delete="askDelete" />
        </template>
      </Column>
    </DataTable>
    <ListFooter :pagination="store.pagination" :count="store.items.length" :local-filter="hasLocalFilter" @page="onPage" />
  </div>

  <div v-else class="card flex items-center justify-center py-12">
    <ProgressSpinner v-if="loading.loading" style="width: 2rem; height: 2rem" />
    <span v-else class="text-surface-500">Sin entidad</span>
  </div>
</template>

<script setup lang="ts">
/**
 * Listado genérico de una entidad (`/lista/:entity`): columnas configurables
 * (ocultar, reordenar), filtros por columna, orden, paginación, edición en
 * línea, selección múltiple y borrado. El estado persiste en el store de la
 * entidad; los filtros viven en `useListFilters`.
 */
import { computed, ref, watch } from "vue";
import { useConfirm } from "primevue/useconfirm";
import type { DataTableCellEditCompleteEvent, DataTableColumnReorderEvent } from "primevue/datatable";
import { router } from "@/app/router";
import { getEntity } from "@/core/entities/registry";
import { useSchemaStore } from "@/core/entities/schema";
import { entityNameFromSlug } from "@/core/entities/slug";
import type { CollectionFieldConfig, EntityStore } from "@/core/entities/types";
import { useLoadingStore } from "@/core/loading";
import { notify } from "@/core/notify";
import ListActions from "./list/ListActions.vue";
import ListCell from "./list/ListCell.vue";
import ListCellEditor from "./list/ListCellEditor.vue";
import ListFooter from "./list/ListFooter.vue";
import ListToolbar from "./list/ListToolbar.vue";
import { idDisplay, nextOrder, sortDirection, toEditedInput } from "./list/listUtils";
import { useListFilters } from "./list/useListFilters";

const SORT_ICONS = { asc: "sort-ascending", desc: "sort-descending", none: "arrows-sort" } as const;
const DEFAULT_PAGE_SIZE = 10;

const props = withDefaults(defineProps<{ entity: string | string[] }>(), { entity: "" });

const schema = useSchemaStore();
const loading = useLoadingStore();
const confirm = useConfirm();

const entityName = computed(() => entityNameFromSlug((Array.isArray(props.entity) ? props.entity[0] : props.entity) ?? ""));
const store = computed<EntityStore | null>(() => (schema.find(entityName.value) ? getEntity(entityName.value) : null));

// Columnas ----------------------------------------------------------------
const visibleColumns = computed(() => (store.value?.columns ?? []).filter((column) => column.visible !== false));
const hiddenColumns = computed(() => (store.value?.columns ?? []).filter((column) => column.visible === false));

const { filters, filterNodes, hasLocalFilter, visibleItems, rebuild, hydrate, highlightFor } = useListFilters(store, visibleColumns);

function setColumnVisible(field: string, visible: boolean) {
  const column = store.value?.columns.find((c) => c.field === field);
  if (column) column.visible = visible;
  rebuild();
}

/** Aplica el drag de PrimeVue a `store.columns`, dejando las ocultas en su sitio. */
function onColumnReorder(event: DataTableColumnReorderEvent) {
  const current = store.value;
  if (!current) return;
  const visible = [...visibleColumns.value];
  const [moved] = visible.splice(event.dragIndex, 1);
  if (!moved) return;
  visible.splice(event.dropIndex, 0, moved);
  let index = 0;
  current.columns = current.columns.map((column) => (column.visible === false ? column : (visible[index++] ?? column)));
}

// Orden y paginación ------------------------------------------------------
/** Ordenable si la configuración no lo impide y el backend acepta el campo. */
function isSortable(column: CollectionFieldConfig) {
  return column.sortable !== false && Boolean(store.value?.metadata.orderFields.includes(column.field));
}

function toggleSort(field: string) {
  const current = store.value;
  if (!current) return;
  current.order = nextOrder(current.order, field);
  void current.fetchItems();
}

function onPage({ page, rows }: { page: number; rows: number }) {
  const current = store.value;
  if (!current?.pagination) return;
  current.pagination.currentPage = page;
  current.pagination.itemsPerPage = rows;
  void current.fetchItems();
}

// Selección ---------------------------------------------------------------
const selectionMode = ref(false);
const selection = ref<unknown[]>([]);

function toggleSelection() {
  selectionMode.value = !selectionMode.value;
  selection.value = [];
}

// Edición en línea --------------------------------------------------------
const canEdit = computed(() => Boolean(store.value?.metadata.update));

function canEditCell(column: CollectionFieldConfig) {
  const mutation = store.value?.metadata.update;
  return column.field !== "id" && Boolean(mutation?.inputFields.some((input) => input.name === column.field));
}

/** Guarda solo el campo editado (`{ id, campo }`). */
async function onCellEditComplete(event: DataTableCellEditCompleteEvent) {
  const current = store.value;
  if (!current || event.value === event.newValue) return;
  try {
    await current.update({
      id: (event.data as { id: unknown }).id,
      [event.field]: toEditedInput(current.metadata, event.field, event.newValue),
    });
    await current.fetchItems();
    notify.success("Cambio guardado");
  } catch (cause) {
    notify.error(cause instanceof Error ? cause.message : String(cause));
  }
}

// Acciones de fila --------------------------------------------------------
function onEdit(item: unknown) {
  const id = idDisplay((item as { id?: unknown }).id);
  void router.push({ name: "entity-form", params: { entity: entityName.value, id } });
}

function askDelete(item: unknown) {
  const current = store.value;
  const id = (item as { id?: string | number }).id;
  if (!current || id === undefined) return;
  confirm.require({
    header: "Confirmar eliminación",
    message: "¿Eliminar este registro? Esta acción no se puede deshacer.",
    acceptProps: { label: "Eliminar", severity: "danger" },
    rejectProps: { label: "Cancelar", severity: "secondary" },
    accept: async () => {
      try {
        await current.remove(id);
        await current.fetchItems();
        notify.success("Registro eliminado");
      } catch (cause) {
        notify.error(cause instanceof Error ? cause.message : String(cause));
      }
    },
  });
}

// Carga -------------------------------------------------------------------
/** Opciones de los filtros de relación (antes de construir los inputs). */
function preloadRelationOptions(current: EntityStore) {
  return current.columns
    .filter((column) => column.filterable !== false)
    .map((column) => current.metadata.fields.find((field) => field.name === column.field))
    .filter((field) => field?.isRelation)
    .map((field) => getEntity(field!.namedType).loadFullList());
}

async function load(current: EntityStore, forceConfig = false) {
  await current.init(forceConfig);
  hydrate();
  rebuild();
  await current.fetchItems();
  await Promise.all(preloadRelationOptions(current));
  rebuild();
}

/** Vuelve a la vista por defecto: filtros, orden, página, columnas y selección. */
async function resetView() {
  const current = store.value;
  if (!current) return;
  current.filters = {};
  current.order = [];
  if (current.pagination) {
    current.pagination.currentPage = 1;
    current.pagination.itemsPerPage = DEFAULT_PAGE_SIZE;
  }
  selectionMode.value = false;
  selection.value = [];
  await load(current, true);
}

watch(
  entityName,
  (name) => {
    const entity = name ? schema.find(name) : null;
    if (!entity) return notify.error(name ? `Entidad "${name}" no encontrada en el schema GraphQL` : "Entidad no especificada");
    if (!entity.queryCollection) return notify.error(`"${name}" no expone una colección consultable`);
    void load(getEntity(name));
  },
  { immediate: true },
);
</script>

<style scoped>
.col-head {
  display: flex;
  flex-direction: column; /* Stacks children vertically from top to bottom */
  min-width: 300px;
  justify-content: end;
  padding: 0 15px;
  gap: 5px;
}
.column-filter-input {
  height: 0px;
  overflow: hidden;
  transition: height var(--transition);
  &.show-filter {
    height: 42px;
  }
}

:deep(.col-actions) {
  /* position: sticky; */
  /* right: 0; */
  padding: 0px 0.5rem;
  /* max-width: 80px; */
  box-shadow: -4px 0 8px rgb(0 0 0 / 0.06);
  background: transparent;
  backdrop-filter: blur(10px);
}

:deep(.p-datatable-thead > tr > th.col-actions) {
  /* z-index: 3; */
  /* background: var(--p-datatable-header-background); */
  background: transparent;
}

:deep(.p-datatable-tbody > tr > td.col-actions) {
  /* z-index: 2; */
  /* background: var(--p-datatable-row-background); */
  background: transparent;
}
:deep(.p-datatable-mask) {
  /*background-color: var(--p-surface-500);*/
  /*opacity: 0.8;*/
  backdrop-filter: blur(6px);
  background: transparent !important;
}
</style>
