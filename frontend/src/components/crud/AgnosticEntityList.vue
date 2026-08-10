<template>
  <div class="space-y-3 bg-amber">
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <SelectButton
          v-model="globalOperator"
          :options="[
            { label: 'OR', value: 'OR' },
            { label: 'AND', value: 'AND' },
          ]"
          option-label="label"
          option-value="value"
          title="Combinación global de filtros"
        />
      </div>
      <Button
        v-if="canCreate"
        label="Nuevo"
        icon="pi pi-plus"
        size="small"
        @click="emit('create')"
      />
    </div>

    <div
      v-if="actionError"
      class="p-3 rounded border border-red-200 dark:border-red-800 text-sm text-red-600 dark:text-red-400"
    >
      Error al eliminar: {{ actionError }}
    </div>

    <Fluid>
      <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
        <template v-for="col in columns.filter((c) => c.filterable)" :key="col.field">
          <div class="relative">
            <FormKit
              :type="filterComponent(col)"
              :model-value="inputModel(col)"
              :label="col.label"
              :debounce="500"
              :multiple="col.kind === 'relation' || col.kind === 'enum' || col.kind === 'boolean'"
              :selection-mode="col.kind === 'date' ? 'range' : undefined"
              :date-format="col.kind === 'date' ? 'yy-mm-dd' : undefined"
              :show-icon="col.kind === 'date'"
              :show-clear="col.kind === 'date' || col.kind === 'boolean'"
              :options="filterOptions(col)"
              option-label="label"
              option-value="value"
              placeholder="Filtrar…"
              @update:model-value="
                col.kind === 'date'
                  ? onDate(col, $event)
                  : col.kind === 'relation' || col.kind === 'enum' || col.kind === 'boolean'
                    ? onSelect(col, $event)
                    : onText(col, $event)
              "
            />
            <SelectButton
              v-if="col.kind === 'relation'"
              v-model="selectOp[col.field]"
              class="mt-1"
              :options="[
                { label: 'OR', value: 'OR' },
                { label: 'AND', value: 'AND' },
              ]"
              option-label="label"
              option-value="value"
              size="small"
            />
          </div>
        </template>
      </div>
    </Fluid>

    <div v-if="isLoading" class="text-sm text-muted-color py-6 text-center animate-pulse">
      Cargando…
    </div>
    <div v-else-if="error" class="text-sm text-red-500 py-6 text-center">
      Error: {{ (error as Error).message }}
    </div>

    <div v-else class="card overflow-hidden">
      <!-- <card>
        <template #content> -->
          <DataTable
            :value="visibleRows"
            scrollable
            scroll-height="flex"
            striped-rows
            size="small"
            sort-mode="custom"
            v-model:sort-field="sortField"
            v-model:sort-order="sortOrder"
            empty-message="Sin registros"
          >
            <Column
              v-for="col in columns"
              :key="col.field"
              :field="col.field"
              :header="col.label"
              :sortable="col.sortable"
            >
              <template #body="{ data }">
                <span class="text-sm">{{ cellValue(col, data[col.field]) }}</span>
              </template>
            </Column>
            <Column header="" frozen align-frozen="right" :style="{ minWidth: '96px' }">
              <template #body="{ data }">
                <div class="flex justify-end gap-1">
                  <Button
                    v-if="canUpdate"
                    icon="pi pi-pencil"
                    text
                    rounded
                    size="small"
                    title="Editar"
                    @click="emit('edit', data)"
                  />
                  <Button
                    v-if="canDelete"
                    icon="pi pi-trash"
                    text
                    rounded
                    size="small"
                    severity="danger"
                    title="Eliminar"
                    @click="confirmRemove(data)"
                  />
                </div>
              </template>
            </Column>
          </DataTable>
        <!-- </template>
      </card> -->
    </div>

    <Paginator
      v-if="shape !== 'flat'"
      :first="pagination.first"
      :rows="pagination.rows"
      :total-records="totalRecords"
      :rows-per-page-options="[25, 50, 100]"
      @update:first="pagination.first = $event"
      @update:rows="onRowsChange"
      @page="onPage"
    />
  </div>
</template>
<script setup lang="ts">
import { computed, reactive, ref, toValue } from "vue";
import type { GraphQLSchema } from "graphql";
import { isEnumType } from "graphql";
import { useCollection, useEntityMutations } from "@graphql-orm/vue";
import { buildEntityDescriptor, shortId, type FindAllParams } from "@graphql-orm/core";
import { buildColumns, type CrudColumn } from "@/crud/entity-meta";
import { matchesFilters, type ColumnFilter, type GlobalOperator } from "@/crud/filters";
import { displayName, numericSortId } from "@/crud/relation-display";
import { useRelationOptions, type RelationOption } from "@/crud/use-relation-options";

const props = defineProps<{ entity: string; schema: GraphQLSchema }>();

const emit = defineEmits<{
  (e: "create"): void;
  (e: "edit", row: Record<string, unknown>): void;
}>();

const columns = computed<CrudColumn[]>(() => buildColumns(props.schema, props.entity));

const descriptor = computed(() => buildEntityDescriptor(props.schema, props.entity));

const shape = computed(() => descriptor.value.queries.collection?.collectionShape ?? "flat");

const canCreate = computed(() => !!descriptor.value.mutations.create);
const canUpdate = computed(() => !!descriptor.value.mutations.update);
const canDelete = computed(() => !!descriptor.value.mutations.delete);

const pagination = reactive({ first: 0, rows: 25 });
let afterCursor: string | undefined;
let beforeCursor: string | undefined;

const sortField = ref<string>("id");
const sortOrder = ref<-1 | 1>(-1);

const textFilters = reactive<Record<string, string>>({});
const selectFilters = reactive<Record<string, Array<string | number | boolean>>>({});
const selectOp = reactive<Record<string, "OR" | "AND">>({});
const dateFilters = reactive<Record<string, unknown>>({});
const globalOperator = ref<GlobalOperator>("OR");

const params = computed<FindAllParams | undefined>(() => {
  if (shape.value === "relay") {
    return beforeCursor
      ? { last: pagination.rows, before: beforeCursor }
      : { first: pagination.rows, after: afterCursor };
  }
  if (shape.value === "page") {
    const currentPage = Math.floor(pagination.first / pagination.rows) + 1;
    return { filters: { currentPage, itemsPerPage: pagination.rows } };
  }
  return undefined;
});

const { data, isLoading, error } = useCollection(props.entity, params);
const { remove } = useEntityMutations(props.entity);
const actionError = ref<string | null>(null);

const relationOptions = useRelationOptions();

const relationOptionsByField = computed<Record<string, RelationOption[]>>(() => {
  const map: Record<string, RelationOption[]> = {};
  for (const col of columns.value) {
    if (col.kind === "relation") map[col.field] = toValue(relationOptions.optionsFor(col.typeName));
  }
  return map;
});

const totalRecords = computed(() => data.value?.totalCount ?? data.value?.items.length ?? 0);

function enumValues(typeName: string): RelationOption[] {
  const type = props.schema.getType(typeName);
  if (!type || !isEnumType(type)) return [];
  return type.getValues().map((v) => ({ label: v.name, value: v.name }));
}

function filterComponent(col: CrudColumn): string {
  if (col.kind === "relation" || col.kind === "enum" || col.kind === "boolean")
    return "MultiSelect";
  if (col.kind === "date") return "DatePicker";
  return "InputText";
}

function filterOptions(col: CrudColumn): RelationOption[] {
  if (col.kind === "relation") return relationOptionsByField.value[col.field] ?? [];
  if (col.kind === "enum") return enumValues(col.typeName);
  if (col.kind === "boolean")
    return [
      { label: "Sí", value: true },
      { label: "No", value: false },
    ];
  return [];
}

const filters = computed<Record<string, ColumnFilter>>(() => {
  const out: Record<string, ColumnFilter> = {};
  for (const col of columns.value) {
    const f: ColumnFilter = {};
    if (col.kind === "date") {
      const d = dateFilters[col.field];
      if (Array.isArray(d)) {
        f.from = fmtDate(d[0]);
        f.to = fmtDate(d[1]);
      }
    } else if (col.kind === "relation" || col.kind === "enum" || col.kind === "boolean") {
      const vals = selectFilters[col.field];
      if (vals && vals.length > 0) {
        f.values = [...vals];
        f.valuesOperator = selectOp[col.field] ?? "OR";
      }
    } else {
      const t = textFilters[col.field];
      if (t && t.trim() !== "") f.text = t;
    }
    out[col.field] = f;
  }
  return out;
});

const visibleRows = computed(() => {
  const rows = data.value?.items ?? [];
  const filtered = rows.filter((r) =>
    matchesFilters(r, columns.value, filters.value, globalOperator.value),
  );
  const field = sortField.value || "id";
  const order = sortOrder.value;
  return [...filtered].sort((a, b) => {
    if (field === "id") return (numericSortId(a) - numericSortId(b)) * order;
    const av = String(a[field] ?? "");
    const bv = String(b[field] ?? "");
    return av.localeCompare(bv, "es", { numeric: true }) * order;
  });
});

function fmtDate(v: unknown): string {
  if (!v) return "";
  if (v instanceof Date) return v.toISOString().slice(0, 10);
  return String(v).slice(0, 10);
}

function onText(col: CrudColumn, v: unknown) {
  textFilters[col.field] = (v as string) ?? "";
}

function onSelect(col: CrudColumn, v: unknown) {
  selectFilters[col.field] = (v as Array<string | number | boolean>) ?? [];
}

function onDate(col: CrudColumn, v: unknown) {
  dateFilters[col.field] = v;
}

function inputModel(col: CrudColumn): unknown {
  if (col.kind === "date") return dateFilters[col.field];
  if (col.kind === "relation" || col.kind === "enum" || col.kind === "boolean")
    return selectFilters[col.field];
  return textFilters[col.field];
}

function onPage(e: { first: number; rows: number }) {
  if (shape.value === "relay") {
    const forward = e.first >= pagination.first;
    if (forward) {
      beforeCursor = undefined;
      afterCursor = data.value?.pageInfo?.endCursor;
    } else {
      afterCursor = undefined;
      beforeCursor = data.value?.pageInfo?.startCursor;
    }
  }
  pagination.first = e.first;
  pagination.rows = e.rows;
}

function onRowsChange(rows: number) {
  pagination.first = 0;
  pagination.rows = rows;
  afterCursor = undefined;
  beforeCursor = undefined;
}

async function confirmRemove(row: Record<string, unknown>) {
  if (!window.confirm(`¿Eliminar ${props.entity} "${displayName(row)}"?`)) return;
  actionError.value = null;
  try {
    await remove(String(row.id));
  } catch (e) {
    actionError.value = (e as Error).message;
  }
}

function cellValue(col: CrudColumn, value: unknown): string {
  if (value === null || value === undefined || value === "") return "—";
  if (col.kind === "relation") return displayName(value as Record<string, unknown>);
  if (col.kind === "boolean") return value ? "Sí" : "No";
  if (col.field === "id") return shortId(String(value));
  if (col.kind === "date") return String(value).replace("T", " ").slice(0, 19);
  return String(value);
}
</script>
