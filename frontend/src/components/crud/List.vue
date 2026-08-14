<template>
  <div>
    <PageHead :title="pageTitle" :subtitle="subtitle">
      <template v-if="schema?.queryCollection">
        <Button icon="pi pi-plus" label="Nuevo" size="small" @click="emit('create')" />
      </template>
    </PageHead>

    <div v-if="error" class="card">
      <Message severity="warn" :closable="false">{{ error }}</Message>
    </div>

    <div v-else-if="schema" class="card flex flex-col overflow-hidden" style="min-height: 400px">
      <div v-if="hiddenCount > 0" class="flex items-center justify-end border-b px-2 py-1">
        <button
          class="relative flex h-7 w-7 items-center justify-center rounded-full text-surface-600 hover:bg-surface-100"
          :aria-label="`${hiddenCount} columnas ocultas`"
          @click="hiddenPopover?.toggle($event)"
        >
          <i class="pi pi-columns text-sm" />
          <span
            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-surface-900 px-1 text-[10px] font-semibold text-surface-0"
          >
            {{ hiddenCount }}
          </span>
        </button>
        <Popover ref="hiddenPopover">
          <div class="flex flex-col gap-1 p-2">
            <span class="px-2 pb-1 text-xs font-semibold text-surface-500">Columnas ocultas</span>
            <button
              v-for="col in hiddenColumns"
              :key="col.field"
              class="flex cursor-pointer items-center justify-between gap-6 rounded px-2 py-1 text-left text-sm text-surface-700 hover:bg-surface-100"
              @click="restoreColumn(col.field)"
            >
              <span>{{ col.label ?? col.field }}</span>
              <i class="pi pi-eye text-xs text-surface-500" />
            </button>
          </div>
        </Popover>
      </div>

      <DataTable
        :value="visibleItems"
        :loading="loading"
        row-key="id"
        scrollable
        scroll-height="flex"
        :removable-sort="true"
        reorderable-columns
        @sort="onSort"
        @column-reorder="onColumnReorder"
      >
        <Column
          v-for="col in visibleColumns"
          :key="col.field"
          :field="col.field"
          :sortable="isSortable(col)"
        >
          <template #header>
            <div class="flex min-w-0 flex-col gap-1">
              <div class="flex items-center justify-between gap-1">
                <span class="truncate font-semibold capitalize">{{ col.label ?? col.field }}</span>
                <Button
                  icon="pi pi-eye-slash"
                  rounded
                  text
                  severity="secondary"
                  size="small"
                  class="h-6 w-6 shrink-0"
                  :aria-label="`Ocultar columna ${col.label ?? col.field}`"
                  @click="hideColumn(col.field)"
                />
              </div>
              <FormKitSchema v-if="filterFor(col.field)" :schema="[filterFor(col.field)!]" />
            </div>
          </template>
          <template #body="{ data }">
            <span>{{ cellDisplay(data, col.field) }}</span>
          </template>
        </Column>

        <Column
          header="Acciones"
          header-class="col-actions"
          body-class="col-actions"
          :exportable="false"
          reorderable-column="false"
        >
          <template #body="{ data }">
            <div class="flex justify-end gap-1">
              <Button
                icon="pi pi-pencil"
                rounded
                text
                severity="secondary"
                size="small"
                aria-label="Editar"
                @click="emit('edit', data)"
              />
              <Button
                icon="pi pi-trash"
                rounded
                text
                severity="danger"
                size="small"
                aria-label="Eliminar"
                @click="askDelete(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t p-2">
        <span v-if="hasLocalFilter" class="text-xs text-surface-500">
          Filtro local: aplica sobre la página cargada
        </span>
        <span
          v-else-if="!isPageConnection && schema.collectionKind"
          class="text-xs text-surface-500"
        >
          {{ store?.pagination.totalCount ?? 0 }} registros
        </span>
        <span v-else></span>
        <Paginator
          v-if="isPageConnection && store"
          :rows="store.pagination.itemsPerPage"
          :first="(store.pagination.currentPage - 1) * store.pagination.itemsPerPage"
          :total-records="store.pagination.totalCount"
          :rows-per-page-options="[10, 25, 50]"
          template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
          @page="onPage"
        />
      </div>
    </div>

    <div v-else class="card flex items-center justify-center py-12">
      <ProgressSpinner v-if="loading" style="width: 2rem; height: 2rem" />
      <span v-else class="text-surface-500">Sin entidad</span>
    </div>

    <Dialog
      v-model:visible="confirmVisible"
      modal
      header="Confirmar eliminación"
      :style="{ width: '25rem' }"
    >
      <span class="text-surface-600 block mb-6">
        ¿Eliminar este registro? Esta acción no se puede deshacer.
      </span>
      <div class="flex justify-end gap-2">
        <Button label="Cancelar" severity="secondary" @click="confirmVisible = false" />
        <Button label="Eliminar" severity="danger" :loading="deleting" @click="confirmDelete" />
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, useId, watch } from "vue";
import { FormKitSchema } from "@formkit/vue";
import type { FormKitSchemaNode } from "@formkit/core";
import type { DataTableSortEvent, DataTableColumnReorderEvent } from "primevue/datatable";
import type Popover from "primevue/popover";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import { useEntityRegistry } from "@/composables/useEntityRegistry";
import type { EntitySchema } from "@/lib/apollo/types";
import type { CollectionFieldConfig, EntityStore } from "@/stores/entities/types";
import {
  cellDisplay,
  cellLabel,
  cellValue,
  fieldKind,
  isEmptyFilterValue,
  noServerFilter,
  rangeToIso,
  resolveFilterArgs,
  type FilterFieldKind,
} from "./listUtils";

const emit = defineEmits<{
  edit: [item: unknown];
  create: [];
}>();

const props = withDefaults(defineProps<{ entity: string | string[] }>(), { entity: "" });

const entityName = computed(() => {
  const raw = Array.isArray(props.entity) ? props.entity[0] : props.entity;
  return raw ?? "";
});

const schemaRepo = useSchemaRepositoryStore();
const registry = useEntityRegistry();

const schema = computed<EntitySchema | null>(() =>
  entityName.value ? schemaRepo.getEntity(entityName.value) : null,
);
const store = computed<EntityStore | null>(() =>
  schema.value ? registry.getEntity(entityName.value) : null,
);

const error = ref("");
const loading = ref(false);
const filters = reactive<Record<string, unknown>>({});
const resetKey = ref(0);

/** Id único por instancia: evita colisiones del memo global de FormKitSchema. */
const uid = useId();

const confirmVisible = ref(false);
const deleting = ref(false);
const deleteTarget = ref<Record<string, unknown> | null>(null);

let textTimer: ReturnType<typeof setTimeout> | undefined;

const pageTitle = computed(() => entityName.value || "Listado");
const subtitle = computed(() =>
  schema.value?.queryCollection ? `${entityName.value} · lista dinámica` : "",
);

const visibleColumns = computed<CollectionFieldConfig[]>(() =>
  (store.value?.columns ?? []).filter((col) => col.visible !== false),
);

const columnsByField = computed(() => new Map(visibleColumns.value.map((col) => [col.field, col])));

const hiddenPopover = ref<InstanceType<typeof Popover> | null>(null);

const hiddenColumns = computed<CollectionFieldConfig[]>(() =>
  (store.value?.columns ?? []).filter((col) => col.visible === false),
);
const hiddenCount = computed(() => hiddenColumns.value.length);

function hideColumn(field: string) {
  const currentStore = store.value;
  const col = currentStore?.columns.find((c) => c.field === field);
  if (col) col.visible = false;
}

function restoreColumn(field: string) {
  const currentStore = store.value;
  const col = currentStore?.columns.find((c) => c.field === field);
  if (col) col.visible = true;
}

const isPageConnection = computed(() => schema.value?.collectionKind === "page-connection");

const hasLocalFilter = computed(() =>
  Object.entries(filters).some(([field, value]) => {
    if (isEmptyFilterValue(value)) return false;
    const entity = schema.value;
    const args = entity ? resolveFilterArgs(entity, field) : null;
    return args ? noServerFilter(args) : true;
  }),
);

const visibleItems = computed<unknown[]>(() => {
  const items = store.value?.items ?? [];
  const entity = schema.value;
  if (!hasLocalFilter.value || !entity) return items;
  return items.filter((item) => matchesClientFilter(item, entity));
});

function matchesClientFilter(item: unknown, entity: EntitySchema): boolean {
  return Object.entries(filters).every(([field, value]) => {
    if (isEmptyFilterValue(value)) return true;
    const kind = fieldKind(entity, field);
    const raw = (item as Record<string, unknown>)[field];
    if (kind === "date") {
      const { after, before } = rangeToIso(value);
      if (!after && !before) return true;
      const timestamp = new Date(String(raw ?? "")).getTime();
      if (Number.isNaN(timestamp)) return false;
      const start = after ? new Date(after).getTime() : Number.NEGATIVE_INFINITY;
      const end = before ? new Date(before).getTime() + 86_400_000 : Number.POSITIVE_INFINITY;
      return timestamp >= start && timestamp <= end;
    }
    if (kind === "relation") {
      const entries = Array.isArray(raw) ? raw : [raw];
      const needle = String(value);
      return entries.some((entry) => {
        const record = entry as Record<string, unknown> | null;
        if (!record) return false;
        return (
          String(record.id ?? "") === needle ||
          cellLabel(record).toLowerCase().includes(needle.toLowerCase())
        );
      });
    }
    const needle = String(value).toLowerCase();
    return cellValue(item, field).toLowerCase().includes(needle);
  });
}

function isSortable(col: CollectionFieldConfig): boolean {
  return col.sortable === true || col.sortable === null || col.sortable === undefined;
}

/** Nodo FormKitSchema del filtro de la columna (null si no es filtrable). */
function filterFor(field: string): FormKitSchemaNode | null {
  const col = columnsByField.value.get(field);
  const entity = schema.value;
  if (!col || col.filterable === false || !entity) return null;
  const kind = fieldKind(entity, field);
  const name = `filter_${field}`;
  const base = { key: `${name}_${uid}_${resetKey.value}` };
  switch (kind) {
    case "relation":
      return {
        ...base,
        $cmp: "FormKit",
        props: {
          type: "Select",
          name,
          placeholder: "Todos",
          showClear: true,
          value: filters[field],
          options: relationOptions(entity, field),
          onInput: (value: unknown) => applyFilter(field, kind, value),
        },
      };
    case "date":
      return {
        ...base,
        $cmp: "FormKit",
        class: "rodriguez",
        props: {
          type: "DatePicker",
          name,
          placeholder: "Rango",
          selectionMode: "range",
          showIcon: true,
          showClear: true,
          class: "alcides",
          value: filters[field],
          onInput: (value: unknown) => applyFilter(field, kind, value),
        },
      };
    case "number":
      return {
        ...base,
        $cmp: "FormKit",
        props: {
          type: "InputNumber",
          name,
          placeholder: "Todos",
          showClear: true,
          value: filters[field],
          onInput: (value: unknown) => applyFilter(field, kind, value),
        },
      };
    case "boolean":
      return {
        ...base,
        $cmp: "FormKit",
        props: {
          type: "Select",
          name,
          placeholder: "Todos",
          showClear: true,
          value: filters[field],
          options: [
            { label: "Sí", value: true },
            { label: "No", value: false },
          ],
          onInput: (value: unknown) => applyFilter(field, kind, value),
        },
      };
    default:
      return {
        ...base,
        $formkit: "InputText",
        // props: {
        type: "InputText",
        name,
        placeholder: "Buscar…",
        clearable: true,
        value: filters[field],
        onInput: (value: unknown) => applyFilter(field, kind, value),
        // },
      };
  }
}

function relationOptions(
  entity: EntitySchema,
  field: string,
): Array<{ label: string; value: string }> {
  const entry = entity.fields.find((f) => f.name === field);
  if (!entry) return [];
  const target = registry.getEntity(entry.namedType);
  return target.fullList.map((option) => ({ label: option.label, value: option.id }));
}

function applyFilter(field: string, kind: FilterFieldKind, value: unknown) {
  filters[field] = value;
  clearTimeout(textTimer);
  if (isEmptyFilterValue(value)) {
    commitFilters();
    return;
  }
  if (kind === "text" || kind === "number") {
    textTimer = setTimeout(commitFilters, 500);
  } else {
    commitFilters();
  }
}

function commitFilters() {
  const entity = schema.value;
  const currentStore = store.value;
  if (!entity || !currentStore) return;
  const server: Record<string, unknown> = {};
  for (const [field, value] of Object.entries(filters)) {
    if (isEmptyFilterValue(value)) continue;
    const kind = fieldKind(entity, field);
    const args = resolveFilterArgs(entity, field);
    if (kind === "date") {
      const { after, before } = rangeToIso(value);
      if (args.after && after) server[args.after] = after;
      if (args.before && before) server[args.before] = before;
    } else if (args.single) {
      server[args.single] = serverValue(value, kind);
    }
  }
  currentStore.filters = server;
  currentStore.pagination.currentPage = 1;
  void currentStore.fetchItems();
}

function serverValue(value: unknown, kind: FilterFieldKind): unknown {
  if (kind === "boolean") return value === true || value === "true";
  if (kind === "number") {
    const num = typeof value === "number" ? value : Number(value);
    return Number.isNaN(num) ? value : num;
  }
  return value;
}

function onPage(event: { page: number; rows: number }) {
  const currentStore = store.value;
  if (!currentStore) return;
  currentStore.pagination.currentPage = event.page + 1;
  currentStore.pagination.itemsPerPage = event.rows;
  void currentStore.fetchItems();
}

function onSort(event: DataTableSortEvent) {
  const currentStore = store.value;
  const field = event.sortField;
  if (!currentStore || typeof field !== "string") return;
  currentStore.order =
    event.sortOrder === 0 || event.sortOrder === undefined
      ? []
      : [{ [field]: event.sortOrder === 1 ? "ASC" : "DESC" }];
  void currentStore.fetchItems();
}

/** Reordena `store.columns` según el drag de PrimeVue, preservando las ocultas. */
function onColumnReorder(event: DataTableColumnReorderEvent) {
  const currentStore = store.value;
  if (!currentStore) return;
  const visible = [...visibleColumns.value];
  const [moved] = visible.splice(event.dragIndex, 1);
  if (!moved) return;
  visible.splice(event.dropIndex, 0, moved);
  const visibleFields = new Set(visible.map((col) => col.field));
  const reordered: CollectionFieldConfig[] = [];
  let index = 0;
  for (const col of currentStore.columns) {
    reordered.push(visibleFields.has(col.field) ? (visible[index++] ?? col) : col);
  }
  currentStore.columns = reordered;
}

function askDelete(item: unknown) {
  deleteTarget.value = (item ?? {}) as Record<string, unknown>;
  confirmVisible.value = true;
}

async function confirmDelete() {
  const currentStore = store.value;
  const target = deleteTarget.value;
  if (!currentStore || !target) return;
  deleting.value = true;
  try {
    await currentStore.remove(target.id as string | number);
    confirmVisible.value = false;
    deleteTarget.value = null;
    await currentStore.fetchItems();
  } finally {
    deleting.value = false;
  }
}

function resetFilters() {
  Object.keys(filters).forEach((key) => delete filters[key]);
  resetKey.value += 1;
}

/**
 * Hidrata los filtros locales (campo → valor) desde los filtros persistidos del
 * store (args de servidor), para que los inputs muestren los filtros guardados.
 */
function hydrateFilters(entity: EntitySchema) {
  const currentStore = store.value;
  if (!currentStore) return;
  Object.keys(filters).forEach((key) => delete filters[key]);
  const server = currentStore.filters;
  for (const fieldEntry of entity.fields) {
    const field = fieldEntry.name;
    const kind = fieldKind(entity, field);
    const args = resolveFilterArgs(entity, field);
    if (kind === "date") {
      const after = args.after ? server[args.after] : undefined;
      const before = args.before ? server[args.before] : undefined;
      if (typeof after === "string" && typeof before === "string") {
        filters[field] = [new Date(after), new Date(before)];
      }
    } else if (args.single) {
      const value = server[args.single];
      if (value !== undefined) filters[field] = value;
    }
  }
}

function preloadRelationLists() {
  const entity = schema.value;
  const currentStore = store.value;
  if (!entity || !currentStore) return;
  for (const col of currentStore.columns) {
    if (col.filterable === false) continue;
    const entry = entity.fields.find((f) => f.name === col.field);
    if (entry?.isRelation) void registry.getEntity(entry.namedType).loadFullList();
  }
}

watch(
  entityName,
  async (name) => {
    confirmVisible.value = false;
    deleteTarget.value = null;
    if (!name) {
      error.value = "Entidad no especificada";
      return;
    }
    const entity = schemaRepo.getEntity(name);
    if (!entity) {
      error.value = `Entidad "${name}" no encontrada en el schema GraphQL`;
      return;
    }
    if (!entity.queryCollection) {
      error.value = `"${name}" no expone una colección consultable (queryCollection)`;
      return;
    }
    error.value = "";
    resetFilters();
    const currentStore = registry.getEntity(name);
    loading.value = true;
    try {
      // El store persiste su estado (incluido el orden/visibilidad de columnas);
      // solo se cargan las columnas del backend la primera vez.
      if (currentStore.columns.length === 0) await currentStore.loadColumns();
      hydrateFilters(entity);
      await currentStore.fetchItems();
      preloadRelationLists();
    } finally {
      loading.value = false;
    }
  },
  { immediate: true },
);
</script>

<style scoped>
:deep(.col-actions) {
  position: sticky;
  right: 0;
  box-shadow: -4px 0 8px rgb(0 0 0 / 0.06);
}

:deep(.p-datatable-thead > tr > th.col-actions) {
  z-index: 3;
  background: var(--p-datatable-header-background);
}

:deep(.p-datatable-tbody > tr > td.col-actions) {
  z-index: 2;
  background: var(--p-datatable-row-background);
}
</style>
