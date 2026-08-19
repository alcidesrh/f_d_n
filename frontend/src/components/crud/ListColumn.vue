<template>
  <Column :field="column.field" :sortable="isSortable(column)">
    <template #header>
      <ListHeader
        :column="column"
        :filter-node="filterNode"
        :sortable="isSortable(column)"
        :sort-state="sortState"
        @hide="emit('hide', column.field)"
      />
    </template>
    <template #body="{ data }">
      <ListCell :column="column" :data="data" />
    </template>
    <template v-if="editable" #editor="{ data }">
      <Suspense>
        <ListCellEditor :column="column" :data="data" />
      </Suspense>
    </template>
    <template #sorticon></template>
  </Column>
</template>

<script setup lang="ts">
import type { FormKitSchemaNode } from "@formkit/core";
import type { EntitySchema } from "@/lib/apollo/types";
import type { CollectionFieldConfig } from "@/stores/entities/types";

defineOptions({ name: "ListColumn" });

const props = defineProps<{
  column: CollectionFieldConfig;
  filterNode: FormKitSchemaNode | null;
  editable: boolean;
  sortState: "asc" | "desc" | null;
}>();

const emit = defineEmits<{
  hide: [field: string];
}>();

function isSortable(col: CollectionFieldConfig): boolean {
  return col.sortable === true || col.sortable === null || col.sortable === undefined;
}
</script>
