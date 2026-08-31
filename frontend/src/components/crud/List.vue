<!-- #region Template -->
<template>
  <div>
    <div v-if="store.metadata" class="card flex flex-col overflow-hidden" style="min-height: 400px">
      <Toolbar class="rounded-none border-none! bg-transparent px-2">
        <template #start>
          <span v-if="selectionMode" class="text-sm font-medium text-surface-600">
            {{ selection.length }} seleccionados
          </span>
          <PageHead v-else></PageHead>
        </template>
        <template #end>
          <div class="flex items-center justify-between gap-5 my-4">
            <icon name="square-check" @click="toggleSelection" />
            <OverlayBadge
              v-if="store.hiddenColumns > 0"
              ``
              :value="String(store.hiddenColumns)"
              severity="primary"
              size="small"
            >
              <div @click="hiddenPopover?.toggle($event)">
                <icon name="eye-off" />
              </div>
            </OverlayBadge>
            <Popover ref="hiddenPopover">
              <div class="flex flex-col gap-1 p-2">
                <span class="px-2 pb-1 text-xs font-semibold text-surface-500"
                  >Columnas ocultas</span
                >
                <button
                  v-for="col in (store?.columns ?? []).filter((col) => col.visible === false)"
                  :key="col.field"
                  class="flex cursor-pointer items-center justify-between gap-6 rounded px-2 py-1 text-left text-sm text-surface-700 hover:bg-surface-100"
                  @click="restoreColumn(col.field)"
                >
                  <span>{{ col.label ?? col.field }}</span>
                  <icon name="eye" />

                  <!-- <i class="pi pi-eye text-xs text-surface-500" /> -->
                </button>
              </div>
            </Popover>
            <icon name="rotate-ccw" @click="resetView" />
          </div>
        </template>
      </Toolbar>
      <DataTable
        v-model:selection="selection"
        :value="visibleItems"
        :loading="loadingStore.loading"
        row-key="id"
        scrollable
        scroll-height="flex"
        :removable-sort="true"
        reorderable-columns
        :edit-mode="canEdit ? 'cell' : undefined"
        @column-reorder="onColumnReorder"
        @cell-edit-complete="onCellEditComplete"
      >
        <Column v-for="col in store.visibleColumns" :key="col.field" :field="col.field">
          <template #header>
            <div class="relative">
              <div class="col-head">
                <div class="flex items-center justify-between gap-1 relative">
                  <span class="truncate font-semibold capitalize">{{
                    col.label ?? col.field
                  }}</span>
                  <span class="flex gap-3">
                    <icon
                      class="ml-3"
                      @click.stop="toggleSort(col.field)"
                      v-if="col.sortable"
                      :name="getSortIcon(col.field)"
                    />
                    <icon
                      v-if="filterNodes.has(col.field)"
                      :name="filters[col.field] ? 'filter-filled' : 'filter'"
                      class=""
                      :class="{ 'text-primary': filters[col.field] }"
                      @click.stop="col.showFilter = !col.showFilter"
                    />
                    <icon name="eye" @click="hideColumn(col.field)" class="" />
                  </span>
                </div>
                <div
                  @click.stop
                  class="column-filter-input"
                  :class="{ 'show-filter': col.showFilter }"
                >
                  <FormKitSchema
                    v-if="filterNodes.has(col.field)"
                    :schema="[filterNodes.get(col.field)]"
                  />
                </div>
              </div>
              <div class="absolute bottom-0 border-r border-r-surface-200 h-[40px] w-[3px]"></div>
            </div>
          </template>
          <!-- #region Datatable:body -->
          <template #body="{ data }">
            <ListCell :column="col" :data="data" :filter-value="filterValueFor(col.field)" />
          </template>
          <!-- #endregion -->
          <template v-if="canEditCell(col)" #editor="{ data }">
            <Suspense>
              <ListCellEditor
                :column="col"
                :data="data"
                :filter-value="filterValueFor(col.field)"
              />
            </Suspense>
          </template>
        </Column>
        <!-- #region Editar y Eliminar.  -->
        <Column
          alignFrozen="right"
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
        <!-- #endregion -->
      </DataTable>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t p-2">
        <span v-if="hasLocalFilter" class="text-xs text-surface-500">
          Filtro local: aplica sobre la página cargada
        </span>
        <span v-else-if="!store.pagination" class="text-xs text-surface-500">
          {{ store?.items.length ?? 0 }} registros
        </span>
        <span v-else></span>
        <!-- #region Datatable:paginator -->

        <Paginator
          v-if="store?.pagination"
          :rows="store.pagination.itemsPerPage"
          :first="(store.pagination.currentPage - 1) * store.pagination.itemsPerPage"
          :total-records="store.pagination.totalCount"
          :rows-per-page-options="[10, 25, 50]"
          template=" FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown "
          currentPageReportTemplate="{first} al {last} de {totalRecords}"
          @page="onPage"
        >
          <template #end="slotProps">
            <span class="ml-[10px] font-semibold text-surface-500">{{
              slotProps.state.page * slotProps.state.rows + 1
            }}</span>
            <span class="font-semsibold text-surface-500 mx-[1px]"> al </span>
            <span class="font-semibold text-surface-500">
              {{
                slotProps.state.page * slotProps.state.rows + slotProps.state.rows <
                store.pagination.totalCount
                  ? slotProps.state.page * slotProps.state.rows + slotProps.state.rows
                  : store.pagination.totalCount
              }}
            </span>
            <span class="font-semibqqld text-surface-500 mx-[1px]"> de </span>

            <span class="font-semibold text-surface-500">
              {{ store.pagination.totalCount }}
            </span>
            <!-- {{ slotProps.state.rows }} -->
          </template>
        </Paginator>
        <!-- #endregion Datatable:paginator -->
      </div>
      <!-- #endregion -->
    </div>

    <div v-else class="card flex items-center justify-center py-12">
      <ProgressSpinner v-if="loadingStore.loading" style="width: 2rem; height: 2rem" />
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
import type { FormKitSchemaNode } from "@formkit/core";
import type {
  DataTableCellEditCompleteEvent,
  DataTableColumnReorderEvent,
} from "primevue/datatable";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import { useEntityRegistry } from "@/composables/useEntityRegistry";
import { useToasts } from "@/composables/useToasts";
import router from "@/router";
import type { EntitySchema } from "@/lib/apollo/types";
import type { CollectionFieldConfig, EntityStore } from "@/stores/entities/types";
import {
  cellLabel,
  cellValue,
  isEmptyFilterValue,
  noServerFilter,
  rangeToIso,
  resolveFilterArgs,
  type FilterFieldKind,
} from "./listUtils";
import type { Popover } from "primevue";
// ---------------------------------------------------------------------------
// Props expuestos al padre.
// ---------------------------------------------------------------------------
const props = withDefaults(defineProps<{ entity: string | string[] }>(), { entity: "" });
// ---------------------------------------------------------------------------
// Stores y contexto: schema introspectado, registry de stores y toasts.
// ---------------------------------------------------------------------------
//#region Variables
const loadingStore = useLoadingStore();
const schemaRepo = useSchemaRepositoryStore();
const registry = useEntityRegistry();
const toasts = useToasts();
const hiddenPopover = ref<InstanceType<typeof Popover> | null>(null);

const entityName = computed(() => {
  const raw = Array.isArray(props.entity) ? props.entity[0] : props.entity;
  return entityNameFromSlug(raw) ?? "";
});
const store = computed<EntityStore | null>(() => registry.getEntity(entityName.value) ?? null);
// ---------------------------------------------------------------------------
// Estado local: carga, filtros en vivo (con debounce), clave de remount de
// los inputs de filtro, modo selección y diálogo de confirmación.
// ---------------------------------------------------------------------------
const resetKey = ref(0);

/** Id único por instancia: evita colisiones del memo global de FormKitSchema. */
const uid = useId();

const confirmVisible = ref(false);
const deleting = ref(false);
const deleteTarget = ref<Record<string, unknown> | null>(null);

let textTimer: ReturnType<typeof setTimeout> | undefined;
//#endregion
/**
 * Snapshot debounced de los filtros SIN arg de servidor (aplicados en cliente
 * sobre `visibleItems`). Se actualiza a los 300 ms de dejar de teclear (o al
 * instante al vaciar): mientras se teclea no cambian ni `hasLocalFilter` ni
 * `visibleItems`, así el DataTable no se re-renderiza (ni remonta el header de
 * filtros) y el input no pierde el foco ni se borra el valor tecleado.
 */

// ---------------------------------------------------------------------------
// Computadas de cabecera, editabilidad y tipo de colección.
// ---------------------------------------------------------------------------
const pageTitle = computed(() => entityName.value || "Listado");
const subtitle = computed(() =>
  store.value.metadata?.queryCollection ? `${entityName.value} · lista dinámica` : "",
);
const canEdit = computed(() => Boolean(store.value.metadata?.update));

// ---------------------------------------------------------------------------
// Columnas visibles/ocultas y detección de una vista "activa" (para reset).
// ---------------------------------------------------------------------------
// #region Columnas visibilidad

const hasActiveView = computed(() => {
  const currentStore = store.value;
  if (!currentStore) return false;
  if (store.value.hiddenColumns.value > 0) return true;
  if (selectionMode.value) return true;
  if (currentStore.order.length > 0) return true;
  if (Object.keys(currentStore.filters).length > 0) return true;
  if ((currentStore.pagination?.currentPage ?? 1) > 1) return true;
  if ((currentStore.pagination?.itemsPerPage ?? 10) !== 10) return true;
  return false;
});

function hideColumn(field: string) {
  const currentStore = store.value;
  const col = currentStore?.columns.find((c) => c.field === field);
  if (col) col.visible = false;
  rebuildFilterNodes();
}

function restoreColumn(field: string) {
  const currentStore = store.value;
  const col = currentStore?.columns.find((c) => c.field === field);
  if (col) col.visible = true;
  rebuildFilterNodes();
}
// #endregion
// #region Selection mode
// ---------------------------------------------------------------------------
// Modo selección múltiple: sustituye las acciones de fila por checkboxes.
// ---------------------------------------------------------------------------
const selectionMode = ref(false);
const selection = ref<unknown[]>([]);

function toggleSelection() {
  selectionMode.value = !selectionMode.value;
  if (!selectionMode.value) selection.value = [];
}
// #endregion
// #region Filter
// ---------------------------------------------------------------------------
// Filtros: nodos FormKit por tipo de columna, debounce para texto/número y
// commit al store (resetea la paginación y refetcha).
// Los nodos se construyen con el valor VIGENTE de `filters` y se reconstruyen
// en cada borde de remontaje del header (cambio de items con `flush: 'sync'`,
// debounce de filtros locales, reset, ocultar/restaurar columnas y cambio de
// entidad). El DataTable de PrimeVue remonta el header entero en cada fetch,
// así que los nodos deben llevar SIEMPRE el valor actual de `filters` para que
// el input re-parseado no aparezca vacío.
// ---------------------------------------------------------------------------
const filters = reactive<Record<string, unknown>>({});

const filterNodes = ref<Map<string, FormKitSchemaNode>>(new Map());

function buildFilterNode(field: string): FormKitSchemaNode | null {
  const col = store.value.getColumnByFieldName(field); // columnsByField.value.get(field)
  const entity = store.value.metadata;
  if (!col || col.filterable === false || !entity) return null;
  const kind = store.value.getFieldKind(field);
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
          options: relationOptionsFor(field),
          size: "small",
          onInput: (value: unknown) => applyFilter(field, kind, value),
          outerClass: "mb-0! w-full",
          class: "w-full",
        },
      };
    case "date":
      return {
        ...base,
        $cmp: "FormKit",
        props: {
          type: "DatePicker",
          name,
          placeholder: "Rango",
          selectionMode: "range",
          showIcon: true,
          showClear: true,
          value: filters[field],
          size: "small",
          onInput: (value: unknown) => applyFilter(field, kind, value),
          outerClass: "mb-0! w-full",
          class: "w-full",
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
          size: "small",
          onInput: (value: unknown) => applyFilter(field, kind, value),
          outerClass: "mb-0! w-full",
          class: "w-full",
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
          size: "small",
          onInput: (value: unknown) => applyFilter(field, kind, value),
          outerClass: "mb-0!",
          class: "w-full",
        },
      };
    default:
      return {
        ...base,
        $cmp: "FormKit",
        props: {
          type: "InputText",
          name,
          clearable: true,
          value: filters[field],
          size: "small",
          onInput: (value: unknown) => applyFilter(field, kind, value),
          outerClass: "mb-0!",
          class: "w-full",
        },
      };
  }
}

/** (Re)construye el mapa de nodos de filtro con los valores vigentes de `filters`. */
function rebuildFilterNodes() {
  const next = new Map<string, FormKitSchemaNode>();
  for (const col of store.value.visibleColumns.filter((v) => v.filterable)) {
    const node = buildFilterNode(col.field);
    if (node) next.set(col.field, node);
  }
  filterNodes.value = next;
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
  const entity = store.value.metadata;
  const currentStore = store.value;
  if (!entity || !currentStore) return;
  const server: Record<string, unknown> = {};
  for (const [field, value] of Object.entries(filters)) {
    if (isEmptyFilterValue(value)) continue;
    const kind = store.value.getFieldKind(field);
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
  if (currentStore.pagination) currentStore.pagination.currentPage = 1;
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
// ---------------------------------------------------------------------------
// Filtros cliente (campos sin arg de servidor): se aplican sobre la página ya
// cargada vía `visibleItems`, sin refetch.
// ---------------------------------------------------------------------------
const hasLocalFilter = computed(() =>
  Object.entries(filters).some(([field, value]) => {
    if (isEmptyFilterValue(value)) return false;
    const entity = store.value.metadata;
    const args = entity ? resolveFilterArgs(entity, field) : null;
    return args ? noServerFilter(args) : true;
  }),
);

const visibleItems = computed<unknown[]>(() => {
  const items = store.value?.items ?? [];
  const entity = store.value.metadata;
  if (!hasLocalFilter.value || !entity) return items;
  return items.filter((item) => matchesClientFilter(item, entity));
});

function matchesClientFilter(item: unknown, entity: EntitySchema): boolean {
  return Object.entries(filters).every(([field, value]) => {
    if (isEmptyFilterValue(value)) return true;
    const kind = store.value.getFieldKind(field);
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

// ---------------------------------------------------------------------------
// Resaltado de coincidencias del filtro (CSS Custom Highlight, texto/número).
// Se alimenta de los filtros YA commiteados en el store (no del tecleo en
// vivo) y se refresca al cambiar los `items` con `flush: 'post'`: así el
// resaltado aparece SIEMPRE tras renderizar el resultado de fetchItems y el
// tecleo no re-renderiza las celdas (que le robaban el foco al input).
// ---------------------------------------------------------------------------
const highlightFilters = ref<Record<string, unknown>>({});

function filterValueFor(field: string): unknown {
  const entity = store.value.metadata;
  if (!entity) return undefined;
  const kind = store.value.getFieldKind(entity, field);
  if (kind !== "text" && kind !== "number") return undefined;
  const args = resolveFilterArgs(entity, field);
  if (noServerFilter(args)) return filters[field];
  return highlightFilters.value[field];
}

function buildHighlightFilters() {
  const entity = store.value.metadata;
  const currentStore = store.value;
  if (!entity || !currentStore) return;
  const next: Record<string, unknown> = {};
  for (const fieldEntry of entity.fields) {
    const field = fieldEntry.name;
    const kind = store.value.getFieldKind(field);
    if (kind !== "text" && kind !== "number") continue;
    const args = resolveFilterArgs(entity, field);
    if (noServerFilter(args)) continue;
    const value = args.single ? currentStore.filters[args.single] : undefined;
    if (value !== undefined) next[field] = value;
  }
  highlightFilters.value = next;
}

watch(() => store.value?.items, buildHighlightFilters, { flush: "post" });

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
    const kind = store.value.getFieldKind(field);
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
// #endregion
// #region Sort
// ---------------------------------------------------------------------------
// Orden (estado de la flecha), paginación y reorden de columnas por drag.
// ---------------------------------------------------------------------------
function sortStateFor(field: string): "asc" | "desc" | null {
  const order = store.value?.order[0];
  if (!order) return null;
  const direction = order[field];
  return direction === "ASC" ? "asc" : direction === "DESC" ? "desc" : null;
}
function getSortIcon(field) {
  const sortState = sortStateFor(field);
  if (sortState === "asc") return "sort-ascending";
  if (sortState === "desc") return "sort-descending";
  return "arrows-sort";
}
function toggleSort(field: string) {
  const currentStore = store.value;
  if (!currentStore) return;
  const current = sortStateFor(field);
  let order: string | null = null;
  if (current === null) order = "ASC";
  else if (current === "asc") order = "DESC";
  currentStore.order = order ? [{ [field]: order }] : [];
  void currentStore.fetchItems();
}

function onPage(event: { page: number; rows: number }) {
  const currentStore = store.value;
  if (!currentStore) return;
  currentStore.pagination.currentPage = event.page + 1;
  currentStore.pagination.itemsPerPage = event.rows;
  void currentStore.fetchItems();
}

/** Reordena `store.columns` según el drag de PrimeVue, preservando las ocultas. */
function onColumnReorder(event: DataTableColumnReorderEvent) {
  const currentStore = store.value;
  if (!currentStore) return;
  const visible = [...store.value.visibleColumns];
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
// #endregion
// #region Cell live editing
// ---------------------------------------------------------------------------
// Edición de celdas en línea: payload mínimo (id + campo editado) y
// normalización de fechas/relaciones para el input GraphQL.
// ---------------------------------------------------------------------------
function canEditCell(col: CollectionFieldConfig): boolean {
  const entity = store.value.metadata;
  const mutation = entity?.update;
  if (!entity || !mutation) return false;
  if (col.field === "id") return false;
  return mutation.inputFields.some((field) => field.name === col.field);
}

function relationOptionsFor(field: string): Array<{ label: string; value: string }> {
  const entity = store.value.metadata;
  // console.log(entity)
  if (!entity) return [];
  const entry = entity.fields.find((f) => f.name === field);
  if (!entry) return [];

  const target = registry.getEntity(entry.namedType);
  return target.fullList.map((option) => ({ label: option.label, value: option.id }));
}

async function onCellEditComplete(event: DataTableCellEditCompleteEvent) {
  const currentStore = store.value;
  if (!currentStore) return;
  if (event.value === event.newValue) return;
  const payload = {
    id: (event.data as Record<string, unknown>).id,
    [event.field]: normalizeEditedValue(event.field, event.newValue),
  };
  try {
    await currentStore.update(payload);
    await currentStore.fetchItems();
    toasts.success("Cambio guardado");
  } catch (err) {
    toasts.error(err instanceof Error ? err.message : String(err));
  }
}
/**
 * Normaliza el valor editado antes de mandarlo al input GraphQL: las fechas se
 * serializan a `YYYY-MM-DD` (el backend rechaza datetime completo) y las
 * relaciones se reducen a su IRI (`{ label, value }` → `/api/{plural}/{id}`).
 */
function normalizeEditedValue(field: string, value: unknown): unknown {
  const entity = store.value.metadata;
  if (!entity) return value;
  const entry = entity.fields.find((f) => f.name === field);
  if (!entry) return value;
  if (entry.isRelation) {
    if (value && typeof value === "object") {
      const record = value as Record<string, unknown>;
      return record.value ?? record.id ?? null;
    }
    return value;
  }
  if (/date/i.test(entry.namedType)) {
    if (value instanceof Date) return value.toISOString().slice(0, 10);
    if (typeof value === "string") return value.slice(0, 10);
  }
  return value;
}
// #endregion
// #region Delete
// ---------------------------------------------------------------------------
// Borrado con diálogo de confirmación.
// ---------------------------------------------------------------------------
function onEdit(item: unknown) {
  const record = (item ?? {}) as Record<string, unknown>;
  void router.push({
    name: "entity-form",
    params: { entity: entityName.value, id: String(record.id) },
  });
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
    toasts.success("Registro eliminado");
  } catch (err) {
    toasts.error(err instanceof Error ? err.message : String(err));
  } finally {
    deleting.value = false;
  }
}
// #endregion
// ---------------------------------------------------------------------------
// Restablecer la vista: filtros, orden, paginación, ocultas y selección.
// ---------------------------------------------------------------------------
async function resetView() {
  const currentStore = store.value;
  const entity = store.value.metadata;
  if (!currentStore || !entity) return;
  resetFilters();
  currentStore.filters = {};
  currentStore.order = [];
  if (currentStore.pagination) {
    currentStore.pagination.currentPage = 1;
    currentStore.pagination.itemsPerPage = 10;
  }
  selectionMode.value = false;
  selection.value = [];
  try {
    await currentStore.loadColumns(true);
    rebuildFilterNodes();
    await currentStore.fetchItems();
  } finally {
  }
}

// ---------------------------------------------------------------------------
// Carga inicial / cambio de entidad: valida el schema, hidrata los filtros
// persistidos, carga items y precarga las listas de relaciones.
// ---------------------------------------------------------------------------

function preloadRelationLists() {
  const entity = store.value.metadata;
  const currentStore = store.value;
  if (!entity || !currentStore) return [];
  const loads: Promise<void>[] = [];
  for (const col of currentStore.columns) {
    if (col.filterable === false) continue;
    const entry = entity.fields.find((f) => f.name === col.field);
    if (entry?.isRelation) loads.push(registry.getEntity(entry.namedType).loadFullList());
  }
  return loads;
}

watch(
  entityName,
  async (name) => {
    confirmVisible.value = false;
    deleteTarget.value = null;
    highlightFilters.value = {};
    if (!name) {
      toasts.error("Entidad no especificada");
      return;
    }
    const entity = schemaRepo.getEntityMetadata(name);
    if (!entity) {
      toasts.error(`Entidad "${name}" no encontrada en el schema GraphQL`);
      return;
    }
    if (!entity.queryCollection) {
      toasts.error(`"${name}" no expone una colección consultable (queryCollection)`);
      return;
    }
    resetFilters();
    const currentStore = registry.getEntity(name);
    try {
      // El store persiste su estado (incluido el orden/visibilidad de columnas);
      // `loadColumns` devuelve las ya cargadas si no se fuerza (ver factory.ts).
      await currentStore.loadColumns();
      hydrateFilters(entity);
      rebuildFilterNodes();
      await currentStore.fetchItems();
      // Precarga las listas de relaciones para los Select de filtro. Se esperan
      // ANTES de reconstruir los nodos (una sola vez, al cargar): reconstruir al
      // terminar cada lista remontaba todos los inputs y les borraba el valor.
      await Promise.all(preloadRelationLists());
      rebuildFilterNodes();
    } finally {
    }
  },
  { immediate: true },
);
</script>
<!-- #endregion -->
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
