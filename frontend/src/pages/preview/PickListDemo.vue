<template>
  <div class="p-6">
    <PageHead
      title="PickList Drag & Drop"
      subtitle="Dos listas, reordenar y mover items arrastrando (GSAP)"
    />

    <div class="mt-10 flex flex-wrap items-start gap-10">
      <SortablePickList
        v-model="lists"
        label-a="Disponibles"
        label-b="Asignados"
        :item-height="52"
        :item-width="260"
      >
        <template #item="{ item }">
          <div class="flex h-full items-center gap-3 px-3">
            <icon name="grip-vertical" size="16" class="text-surface-400" />
            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ item.key }}</span>
            <span class="rounded bg-surface-100 px-1.5 py-0.5 text-[11px] text-surface-500">#{{ item.position }}</span>
          </div>
        </template>
      </SortablePickList>

      <div class="card w-full max-w-md">
        <h3 class="mb-2 text-sm font-semibold">Estado sincronizado (v-model)</h3>
        <pre class="overflow-auto rounded-lg bg-surface-100 p-4 text-xs leading-relaxed">{{ pretty }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import SortablePickList from "@/components/common/SortablePickList.vue";
import type { SortablePickListItem } from "@/components/common/SortablePickList.vue";

const A = ["Conductor", "Operadora", "Inspector", "Despachador"].map((key, i) => ({ key, position: i + 1 }));
const B = ["Supervisor", "Planificador", "Auditor"].map((key, i) => ({ key, position: i + 1 }));

const lists = ref<[SortablePickListItem[], SortablePickListItem[]]>([A, B]);

const pretty = computed(() => JSON.stringify(lists.value, null, 2));
</script>