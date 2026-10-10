<template>
  <Teleport v-if="store" to="body" :disabled="!maximized">
    <div class="list-page card" :class="{ 'is-maximized': maximized, 'is-fill': fill }" :aria-modal="maximized || undefined" :role="maximized ? 'dialog' : undefined">
      <ListToolbar
        v-if="features.toolbar"
        :features="features"
        :columns="store.columns"
        :view="store.view"
        :active-filters="filters.active.value.length"
        :selection-mode="modoSeleccion"
        :selected-count="selectedRows.length"
        :acciones="enSelector ? [] : accionesDisponibles"
        :maximized="maximized"
        :configurable="configurable && !enSelector"
        @toggle-selection="toggleSelectionMode"
        @clear-selection="setSelection([])"
        @share="compartir = true"
        @accion="ejecutarAccion"
        @column-visible="setColumnVisible"
        @show-all-columns="showAllColumns"
        @toggle-filters="store.view.filtersOpen = !store.view.filtersOpen"
        @density="store.view.density = $event"
        @layout="store.view.layout = $event"
        @maximize="maximized = !maximized"
        @reset="resetView"
        @configure="emit('configure')"
      >
        <template #start>
          <div v-if="filters.active.value.length" class="list-chips" aria-label="Filtros aplicados">
            <span v-for="field in filters.active.value" :key="field" class="list-chip">
              <span class="text-surface-500">{{ titleOf(field) }}:</span>
              <span class="truncate">{{ filters.describe(field) }}</span>
              <button type="button" class="list-chip__x" :aria-label="`Quitar filtro ${titleOf(field)}`" @click="filters.clear(field)"><icon name="close" size="0.85rem" /></button>
            </span>
            <SelectButton v-if="filters.active.value.length > 1" :model-value="store.view.filterMode" :options="FILTER_MODES" option-label="label" option-value="value" :allow-empty="false" size="small" aria-label="Cómo se combinan los filtros" @update:model-value="filters.setMode($event)" />
            <Button label="Limpiar" size="small" text severity="secondary" @click="filters.clear()" />
          </div>
        </template>
      </ListToolbar>

      <!-- En tarjetas no hay cabecera: filtros y orden van en este panel. -->
      <div v-if="cardsMode && store.view.filtersOpen && (features.filter || features.sort)" class="list-panel">
        <label v-for="column in filterableColumns" :key="column.field" class="list-panel__field">
          <span>{{ titleOf(column.field) }}</span>
          <ListFilterField :kind="filters.kindOf(column.field)" :label="titleOf(column.field)" :model-value="filters.values[column.field]" :options="filters.optionsFor(column.field)" :multiple="filters.isMulti(column.field)" @update:model-value="filters.set(column.field, $event)" />
        </label>
        <div v-if="features.sort && sortableColumns.length" class="list-panel__field">
          <span>Ordenar por</span>
          <div class="flex gap-1">
            <Select :model-value="currentSort?.field ?? null" :options="sortableColumns.map((c) => ({ label: titleOf(c.field), value: c.field }))" option-label="label" option-value="value" placeholder="Sin orden" show-clear size="small" fluid @update:model-value="setSort($event, currentSort?.dir ?? 'asc')" />
            <Button v-if="currentSort" size="small" severity="secondary" outlined :aria-label="currentSort.dir === 'asc' ? 'Ascendente' : 'Descendente'" @click="setSort(currentSort.field, currentSort.dir === 'asc' ? 'desc' : 'asc')">
              <icon :name="currentSort.dir === 'asc' ? 'arrow-upward' : 'arrow-downward'" size="1rem" />
            </Button>
          </div>
        </div>
      </div>

      <DataGrid
        ref="grid"
        class="list-page__grid"
        :columns="gridColumns"
        :rows="store.items"
        :row-key="claveFila"
        :loading="loading.loading"
        :density="store.view.density"
        :layout="store.view.layout"
        :selectable="modoSeleccion"
        :selected-keys="selectedKeys"
        :actions="actionsWidth"
        :reorderable="features.reorder"
        :resizable="features.resize"
        :hideable="features.columns && visibleColumns.length > 1"
        :filters-open="features.filter && store.view.filtersOpen"
        :editing="editing ? { row: editing.row, column: editing.field } : null"
        :row-clickable="modoSeleccion"
        :max-height="fill ? undefined : 'min(72dvh, 56rem)'"
        @mode="cardsMode = $event === 'cards'"
        @sort="toggleSort"
        @filter="openFilter"
        @hide="(field) => setColumnVisible(field, false)"
        @reorder="onColumnReorder"
        @resize="onColumnResize"
        @row-click="onRowClick"
        @cell-click="startEdit"
        @toggle-row="(row, on) => setSelection(toggleRows(selectedRows, [row], on, claveFila))"
        @toggle-page="(on) => setSelection(toggleRows(selectedRows, store!.items, on, claveFila))"
      >
        <template #cell="{ row, column }">
          <ListCell :column="configOf(column.key)" :entity="entityName" :data="row" :filter-value="filters.highlightFor(column.key)" />
        </template>
        <template #editor="{ column }">
          <ListCellEditor v-if="editing" :entity="entityName" :column="configOf(column.key)" :data="editing.draft" @commit="commitEdit" @cancel="editing = null" />
        </template>
        <template #filter="{ column }">
          <ListFilterField :kind="filters.kindOf(column.key)" :label="column.label" :model-value="filters.values[column.key]" :options="filters.optionsFor(column.key)" :multiple="filters.isMulti(column.key)" :input-id="`${uid}-f-${column.key}`" @update:model-value="filters.set(column.key, $event)" />
        </template>
        <template #actions="{ row }">
          <ListActions :item="row" :actions="extraActions" :can-edit="canEditRow" :can-delete="canDeleteRow" @edit="onEdit" @delete="askDelete" @action="openAction" />
        </template>
        <template #empty>
          <icon :name="filters.active.value.length ? 'filter-alt-off-outline' : 'inbox-outline'" size="2rem" />
          <span>{{ filters.active.value.length ? 'Ningún registro coincide con los filtros' : 'Sin registros' }}</span>
          <Button v-if="filters.active.value.length" label="Quitar filtros" size="small" text @click="filters.clear()" />
        </template>
      </DataGrid>

      <ListFooter v-if="features.pagination" :pagination="store.pagination" :count="store.items.length" :page-sizes="pageSizes" @page="onPage" />
      <EnviarPorChatDialog v-if="!enSelector" v-model:visible="compartir" :referencias="referencias" @enviado="toggleSelectionMode" />
      <component :is="activeAction.component" v-if="activeAction" v-model:visible="actionVisible" :item="activeAction.item" @after-hide="activeAction = null" />
      <component :is="activeBulk.component" v-if="activeBulk" v-model:visible="bulkVisible" :ids="activeBulk.ids" @listo="alTerminarAccion" @after-hide="activeBulk = null" />
    </div>
  </Teleport>

  <div v-else class="card flex items-center justify-center py-12">
    <ProgressSpinner v-if="loading.loading" style="width: 2rem; height: 2rem" />
    <span v-else class="text-surface-500">Sin entidad</span>
  </div>
</template>

<script setup lang="ts">
/**
 * Listado genérico de una entidad (`/lista/:entity`) sobre `DataGrid`
 * (ADR-029): columnas configurables (ocultar, reordenar, ancho), filtros por
 * columna en la base de datos (combinados con OR o AND), orden, paginación,
 * edición en línea, selección con acciones (eliminar y las de la entidad) y
 * vista (densidad, tabla/tarjetas, maximizar). El estado persiste en el
 * store de la entidad; los filtros viven en `useListFilters`.
 *
 * Con `v-model:seleccion` es un selector (el del chat, `LISTADO_DE_REGISTROS`):
 * siempre en selección, sin editar ni acciones, y la selección sobrevive al
 * cambiar de página o de filtros. `features` apaga partes en otras vistas.
 */
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, ref, shallowRef, useId, watch, type Component } from "vue";
import { useConfirm } from "primevue/useconfirm";
import { router } from "@/app/router";
import { DEFAULT_PAGE_SIZE, DEFAULT_PAGE_SIZES, defaultView } from "@/core/entities/listView";
import { getEntity } from "@/core/entities/registry";
import { useSchemaStore } from "@/core/entities/schema";
import { entityNameFromSlug } from "@/core/entities/slug";
import type { CollectionFieldConfig, EntityStore, FilterMode } from "@/core/entities/types";
import { useLoadingStore } from "@/core/loading";
import { notify } from "@/core/notify";
import { usePermisosBoleto } from "@/core/venta/permisos";
import EnviarPorChatDialog from "@/shared/chat/EnviarPorChatDialog.vue";
import { anunciarEnPantalla, etiquetaDeFila } from "@/shared/chat/integracion";
import DataGrid from "@/shared/data-grid/DataGrid.vue";
import { moveItem } from "@/shared/data-grid/layout";
import type { GridColumn } from "@/shared/data-grid/types";
import ListActions from "./list/ListActions.vue";
import { entityBulkActions, entityListActions, entityReadOnly, type EntityBulkAction, type EntityListAction } from "./list/listActions";
import ListCell from "./list/ListCell.vue";
import ListCellEditor from "./list/ListCellEditor.vue";
import { resolveListFeatures, type ListFeatures } from "./list/listFeatures";
import ListFilterField from "./list/ListFilterField.vue";
import ListFooter from "./list/ListFooter.vue";
import { keySet, toggleRows } from "./list/listSelection";
import ListToolbar from "./list/ListToolbar.vue";
import { columnTitle, idDisplay, isToMany, nextOrder, sameCellValue, sortDirection, toEditedInput } from "./list/listUtils";
import { useListFilters } from "./list/useListFilters";

const FILTER_MODES: Array<{ label: string; value: FilterMode }> = [
  { label: "Cualquiera", value: "or" },
  { label: "Todos", value: "and" },
];

/** Acción por defecto sobre la selección. */
const ELIMINAR: EntityBulkAction = { key: "eliminar", label: "Eliminar", icon: "delete-outline", severity: "danger" };

const props = withDefaults(
  defineProps<{
    entity: string | string[];
    configurable?: boolean;
    seleccion?: unknown[];
    /** Partes del listado a apagar (o encender) en esta vista; ver `ListFeatures`. */
    features?: Partial<ListFeatures>;
  }>(),
  { entity: "", configurable: false, seleccion: undefined, features: undefined },
);
const emit = defineEmits<{ configure: []; "update:seleccion": [filas: unknown[]] }>();

const uid = useId();
const schema = useSchemaStore();
const loading = useLoadingStore();
const confirm = useConfirm();

const entityName = computed(() => entityNameFromSlug((Array.isArray(props.entity) ? props.entity[0] : props.entity) ?? ""));
const store = computed<EntityStore | null>(() => (schema.find(entityName.value) ? getEntity(entityName.value) : null));
const enSelector = computed(() => props.seleccion !== undefined);

// Qué se ofrece ------------------------------------------------------------
/** Boletos, ventas…: se operan con acciones propias, no se editan ni se eliminan a mano. */
const soloLectura = computed(() => entityReadOnly.has(entityName.value));
const features = computed(() =>
  resolveListFeatures({
    selector: enSelector.value,
    canUpdate: Boolean(store.value?.metadata.update),
    canDelete: Boolean(store.value?.metadata.delete),
    readOnly: soloLectura.value,
    options: store.value?.listOptions,
    overrides: props.features,
  }),
);

// Columnas ----------------------------------------------------------------
const visibleColumns = computed(() => (store.value?.columns ?? []).filter((column) => column.visible !== false));
const columnsByField = computed(() => new Map((store.value?.columns ?? []).map((column) => [column.field, column])));
const configOf = (field: string) => columnsByField.value.get(field) ?? { field };
const titleOf = (field: string) => columnTitle(configOf(field));

const filters = useListFilters(store);

/** Ordenable si la configuración no lo impide y el backend acepta el campo. */
function isSortable(column: CollectionFieldConfig) {
  return column.sortable !== false && Boolean(store.value?.metadata.orderFields.includes(column.field));
}

/** Editable en línea si la mutación de update acepta el campo (las relaciones a muchos, en el formulario). */
function canEditCell(column: CollectionFieldConfig) {
  const metadata = store.value?.metadata;
  if (!metadata?.update || column.field === "id" || isToMany(metadata, column.field)) return false;
  return metadata.update.inputFields.some((input) => input.name === column.field);
}

const gridColumns = computed<GridColumn[]>(() => {
  const order = store.value?.order ?? [];
  const metadata = store.value?.metadata;
  return visibleColumns.value.map((column) => {
    const type = metadata?.fields.find((f) => f.name === column.field)?.namedType;
    return {
      key: column.field,
      label: columnTitle(column),
      width: column.width ?? null,
      sortable: features.value.sort && isSortable(column),
      sort: sortDirection(order, column.field),
      filterable: features.value.filter && filters.isFilterable(column),
      filtered: filters.active.value.includes(column.field),
      editable: features.value.inlineEdit && !modoSeleccion.value && canEditCell(column),
      align: type === "Int" || type === "Float" ? "end" : "start",
    };
  });
});

const filterableColumns = computed(() => (features.value.filter ? visibleColumns.value.filter((c) => filters.isFilterable(c)) : []));
const sortableColumns = computed(() => visibleColumns.value.filter(isSortable));

function setColumnVisible(field: string, visible: boolean) {
  const current = store.value;
  const column = current?.columns.find((c) => c.field === field);
  if (!current || !column) return;
  if (!visible && visibleColumns.value.length <= 1) return;
  column.visible = visible;
  // Solo se piden las columnas visibles: la que aparece necesita sus datos.
  if (visible) void current.fetchItems();
}

function showAllColumns() {
  const current = store.value;
  if (!current) return;
  current.columns.forEach((column) => (column.visible = true));
  void current.fetchItems();
}

/** Aplica el arrastre a `store.columns`, dejando las ocultas en su sitio. */
function onColumnReorder(from: number, to: number) {
  const current = store.value;
  if (!current) return;
  const visible = moveItem(visibleColumns.value, from, to);
  let index = 0;
  current.columns = current.columns.map((column) => (column.visible === false ? column : (visible[index++] ?? column)));
}

function onColumnResize(field: string, width: string | null) {
  const column = store.value?.columns.find((c) => c.field === field);
  if (!column) return;
  // `null` (doble clic en el borde): vuelve al ancho de la configuración.
  column.width = width ?? column.configWidth ?? null;
}

// Orden y paginación ------------------------------------------------------
function toggleSort(field: string) {
  const current = store.value;
  if (!current) return;
  current.order = nextOrder(current.order, field);
  void current.fetchItems();
}

const currentSort = computed(() => {
  const condition = store.value?.order[0];
  const field = condition ? Object.keys(condition)[0] : undefined;
  return field ? { field, dir: condition![field] === "DESC" ? ("desc" as const) : ("asc" as const) } : null;
});

function setSort(field: string | null, dir: "asc" | "desc") {
  const current = store.value;
  if (!current) return;
  current.order = field ? [{ [field]: dir === "asc" ? "ASC" : "DESC" }] : [];
  void current.fetchItems();
}

const pageSizes = computed(() => {
  const sizes = new Set(store.value?.listOptions.pageSizes ?? DEFAULT_PAGE_SIZES);
  const current = store.value?.pagination?.itemsPerPage;
  if (current) sizes.add(current);
  return [...sizes].sort((a, b) => a - b);
});

function onPage({ page, rows }: { page: number; rows: number }) {
  const current = store.value;
  if (!current?.pagination) return;
  current.pagination.currentPage = page;
  current.pagination.itemsPerPage = rows;
  editing.value = null;
  void current.fetchItems();
}

// Filtros -----------------------------------------------------------------
const cardsMode = ref(false);

/** Embudo de una cabecera: abre la fila de filtros y lleva el foco a esa columna. */
async function openFilter(field: string) {
  const current = store.value;
  if (!current) return;
  current.view.filtersOpen = true;
  await nextTick();
  // `field` es un nombre de propiedad (identificador): no hace falta escaparlo.
  document.querySelector<HTMLElement>(`[data-grid-filter="${field}"] input, [data-grid-filter="${field}"] [tabindex="0"]`)?.focus();
}

// Acciones por fila -------------------------------------------------------
const extraActions = computed<EntityListAction[]>(() => (features.value.rowActions ? (entityListActions[entityName.value] ?? []) : []));
const canEditRow = computed(() => features.value.rowActions && !soloLectura.value);
const canDeleteRow = computed(() => features.value.rowActions && !soloLectura.value && Boolean(store.value?.metadata.delete));
/** Ancho de la columna fija de acciones: 2rem por botón. En selección no hay acciones por fila. */
const actionsWidth = computed(() => {
  if (modoSeleccion.value) return null;
  const count = extraActions.value.length + Number(canEditRow.value) + Number(canDeleteRow.value);
  return count ? `${count * 2 + 1}rem` : null;
});

const activeAction = shallowRef<{ component: Component; item: unknown } | null>(null);
const actionVisible = ref(false);

function openAction(action: EntityListAction, item: unknown) {
  activeAction.value = { component: defineAsyncComponent(action.component), item };
  actionVisible.value = true;
}

function onEdit(item: unknown) {
  const id = idDisplay((item as { id?: unknown }).id);
  if (maximized.value) maximized.value = false;
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
        setSelection(selectedRows.value.filter((row) => claveFila(row) !== claveFila(item)));
        await current.fetchItems();
        notify.success("Registro eliminado");
      } catch (cause) {
        notify.error(cause instanceof Error ? cause.message : String(cause));
      }
    },
  });
}

// Selección ---------------------------------------------------------------
const selectionMode = ref(false);
const localSelection = shallowRef<unknown[]>([]);
const modoSeleccion = computed(() => enSelector.value || (features.value.selection && selectionMode.value));
const selectedRows = computed<unknown[]>(() => (enSelector.value ? (props.seleccion ?? []) : localSelection.value));

/** Por id numérico: la fila de la API (IRI) y `{ id: 12 }` son la misma. */
const claveFila = (fila: unknown) => idDisplay((fila as { id?: unknown })?.id);
const selectedKeys = computed(() => keySet(selectedRows.value, claveFila));

function setSelection(rows: unknown[]) {
  if (enSelector.value) emit("update:seleccion", rows);
  else localSelection.value = rows;
}

function toggleSelectionMode() {
  selectionMode.value = !selectionMode.value;
  editing.value = null;
  setSelection([]);
}

function onRowClick(row: unknown) {
  if (!modoSeleccion.value) return;
  setSelection(toggleRows(selectedRows.value, [row], !selectedKeys.value.has(claveFila(row)), claveFila));
}

// Acciones sobre la selección ----------------------------------------------
const permisosBoleto = usePermisosBoleto();
const activeBulk = shallowRef<{ component: Component; ids: number[] } | null>(null);
const bulkVisible = ref(false);

/** Eliminar (si se puede) y las de la entidad que el usuario puede hacer. */
const accionesDisponibles = computed(() => [
  ...(features.value.bulkDelete ? [ELIMINAR] : []),
  ...(entityBulkActions[entityName.value] ?? []).filter((a) => !a.permiso || permisosBoleto[a.permiso]),
]);

async function ejecutarAccion(accion: EntityBulkAction) {
  if (accion.key === ELIMINAR.key) return confirmBulkDelete();
  const ids = selectedRows.value.map((fila) => Number(claveFila(fila))).filter((id) => Number.isInteger(id) && id > 0);
  if (!ids.length) return;
  if (accion.ruta) return void router.push(accion.ruta(ids));
  if (accion.component) {
    activeBulk.value = { component: defineAsyncComponent(accion.component), ids };
    bulkVisible.value = true;
    return;
  }
  try {
    await accion.ejecutar?.(ids);
  } catch (cause) {
    notify.error(cause instanceof Error ? cause.message : String(cause));
  }
}

function confirmBulkDelete() {
  const current = store.value;
  const rows = [...selectedRows.value];
  if (!current || !rows.length) return;
  confirm.require({
    header: "Eliminar seleccionados",
    message: `¿Eliminar ${rows.length === 1 ? "el registro seleccionado" : `los ${rows.length} registros seleccionados`}? Esta acción no se puede deshacer.`,
    acceptProps: { label: "Eliminar", severity: "danger" },
    rejectProps: { label: "Cancelar", severity: "secondary" },
    accept: async () => {
      const failed: unknown[] = [];
      let lastError = "";
      // Uno a uno: un registro con dependencias no debe frenar al resto.
      for (const row of rows) {
        try {
          await current.remove((row as { id: string | number }).id);
        } catch (cause) {
          failed.push(row);
          lastError = cause instanceof Error ? cause.message : String(cause);
        }
      }
      setSelection(failed);
      await current.fetchItems();
      const done = rows.length - failed.length;
      if (done) notify.success(done === 1 ? "Registro eliminado" : `${done} registros eliminados`);
      if (failed.length) notify.error(`${failed.length} no se ${failed.length === 1 ? "pudo" : "pudieron"} eliminar: ${lastError}`);
    },
  });
}

/** Lo que cambió una acción (p. ej. boletos anulados): se vuelve a leer la página. */
async function alTerminarAccion() {
  setSelection([]);
  await store.value?.fetchItems();
}

// Enviar por chat -----------------------------------------------------------
const compartir = ref(false);
/** Los seleccionados como referencias `{ tipo, id }` (el id numérico, no el IRI). */
const referencias = computed(() =>
  selectedRows.value
    .map((item) => Number(claveFila(item)))
    .filter((id) => Number.isInteger(id) && id > 0)
    .map((id) => ({ tipo: entityName.value, id })),
);

// Con el chat abierto encima, lo seleccionado se ofrece para adjuntar.
anunciarEnPantalla(() =>
  enSelector.value || !selectionMode.value
    ? []
    : selectedRows.value.flatMap((fila) => {
        const id = Number(claveFila(fila));
        return Number.isInteger(id) && id > 0 ? [{ tipo: entityName.value, id, etiqueta: etiquetaDeFila(fila as Record<string, unknown>, id, entityName.value) }] : [];
      }),
);

// Edición en línea --------------------------------------------------------
const editing = ref<{ row: string; field: string; draft: Record<string, unknown>; original: unknown } | null>(null);

function startEdit(row: unknown, column: GridColumn) {
  if (!column.editable) return;
  const record = row as Record<string, unknown>;
  editing.value = { row: claveFila(row), field: column.key, draft: { ...record }, original: record[column.key] };
}

/** Guarda solo el campo editado (`{ id, campo }`). */
async function commitEdit() {
  const current = store.value;
  const edit = editing.value;
  editing.value = null;
  if (!current || !edit || sameCellValue(edit.original, edit.draft[edit.field])) return;
  try {
    await current.update({ id: edit.draft.id, [edit.field]: toEditedInput(current.metadata, edit.field, edit.draft[edit.field]) });
    await current.fetchItems();
    notify.success("Cambio guardado");
  } catch (cause) {
    notify.error(cause instanceof Error ? cause.message : String(cause));
  }
}

// Maximizar ---------------------------------------------------------------
const maximized = ref(false);
/** El listado llena su contenedor (maximizado o como selector) en lugar de tener alto máximo. */
const fill = computed(() => maximized.value || enSelector.value);

function onKeydown(event: KeyboardEvent) {
  if (event.key === "Escape" && maximized.value && !editing.value && !document.querySelector(".p-overlay-mask, .p-select-overlay, .p-popover")) maximized.value = false;
}
watch(maximized, (on) => {
  if (on) document.addEventListener("keydown", onKeydown);
  else document.removeEventListener("keydown", onKeydown);
});
onBeforeUnmount(() => document.removeEventListener("keydown", onKeydown));

// Carga -------------------------------------------------------------------
async function load(current: EntityStore, forceConfig = false) {
  await current.init(forceConfig);
  filters.hydrate();
  await current.fetchItems();
  await filters.preloadOptions(current.columns);
}

/** Vuelve a la vista por defecto: filtros, orden, página, columnas, vista y selección. */
async function resetView() {
  const current = store.value;
  if (!current) return;
  current.filters = {};
  current.order = [];
  current.view = defaultView(current.listOptions);
  if (current.pagination) {
    current.pagination.currentPage = 1;
    current.pagination.itemsPerPage = current.listOptions.pageSize ?? DEFAULT_PAGE_SIZE;
  }
  selectionMode.value = false;
  maximized.value = false;
  editing.value = null;
  setSelection([]);
  await load(current, true);
}

watch(
  entityName,
  (name) => {
    editing.value = null;
    if (entityBulkActions[name]) void permisosBoleto.cargar();
    const entity = name ? schema.find(name) : null;
    if (!entity) return notify.error(name ? `Entidad "${name}" no encontrada en el schema GraphQL` : "Entidad no especificada");
    if (!entity.queryCollection) return notify.error(`"${name}" no expone una colección consultable`);
    void load(getEntity(name));
  },
  { immediate: true },
);
</script>

<style scoped>
.list-page {
  display: flex;
  min-width: 0;
  flex-direction: column;
}
.list-page.is-fill {
  height: 100%;
  min-height: 0;
}
.list-page.is-fill .list-page__grid {
  flex: 1 1 auto;
}
.list-page.is-maximized {
  position: fixed;
  inset: 0;
  z-index: 1000;
  padding: 0.75rem clamp(0.75rem, 2vw, 1.5rem);
  background: var(--p-content-background);
  animation: list-maximize 0.18s var(--ease);
}
@keyframes list-maximize {
  from {
    opacity: 0;
    transform: scale(0.985);
  }
}

.list-chips {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}
.list-chip {
  display: inline-flex;
  max-width: 18rem;
  align-items: center;
  gap: 0.25rem;
  padding: 0.125rem 0.25rem 0.125rem 0.625rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 999px;
  font-size: 0.8125rem;
}
.list-chip__x {
  display: inline-grid;
  width: 1.25rem;
  height: 1.25rem;
  flex: none;
  place-items: center;
  border-radius: 999px;
  cursor: pointer;
}
.list-chip__x:hover {
  background: color-mix(in srgb, var(--p-surface-500) 15%, transparent);
}

.list-panel {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 14rem), 1fr));
  gap: 0.625rem 0.75rem;
  margin-bottom: 0.75rem;
  padding: 0.75rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.75rem;
}
.list-panel__field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
</style>
