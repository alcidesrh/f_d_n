<template>
  <!-- <Toolbar class="rounded-none border-none! bg-transparent px-2">
    <template #end> -->
  <div class="mb-6 px-5 py-2 rounded-md flex flex-wrap items-center justify-end w-fit ml-auto gap-5">
    <button v-if="selectable" type="button" class="tap-target" aria-label="Modo selección" @click="emit('toggle-selection')">
      <icon name="check-box-outline" :class="{ 'text-primary': selectionMode }" />
    </button>
    <button v-if="selectable && selectionMode && selectedCount > 0" type="button" class="tap-target flex items-center gap-1.5 text-sm text-primary" :aria-label="`Enviar ${selectedCount} por chat`" v-tooltip.bottom="'Enviar por chat'" @click="emit('share')">
      <icon name="forum-outline" class="text-primary" />{{ selectedCount }}
    </button>
    <OverlayBadge v-if="hiddenColumns.length > 0" :value="String(hiddenColumns.length)" severity="primary" size="small">
      <button type="button" class="tap-target" :aria-label="`${hiddenColumns.length} columnas ocultas`" @click="popover?.toggle($event)">
        <icon name="visibility-off-outline" />
      </button>
    </OverlayBadge>
    <Popover ref="popover">
      <div class="flex flex-col gap-1 p-2">
        <span class="px-2 pb-1 text-xs font-semibold text-surface-500">Columnas ocultas</span>
        <button v-for="column in hiddenColumns" :key="column.field" type="button" class="flex cursor-pointer items-center justify-between gap-6 rounded px-2 py-1 text-left text-sm text-surface-700 hover:bg-surface-100" @click="emit('restore', column.field)">
          <span>{{ column.label ?? column.field }}</span>
          <icon name="visibility-outline" />
        </button>
      </div>
    </Popover>
    <button v-if="configurable" type="button" class="tap-target" aria-label="Configurar entidad" @click="emit('configure')">
      <icon name="settings-outline" />
    </button>
    <button type="button" class="tap-target" aria-label="Restablecer vista" @click="emit('reset')">
      <icon name="ink-eraser-outline" />
    </button>
  </div>
  <!-- </template>
  </Toolbar> -->
</template>

<script setup lang="ts">
import { ref } from "vue";
import type { Popover as PopoverType } from "primevue";
import type { CollectionFieldConfig } from "@/core/entities/types";

withDefaults(defineProps<{
  selectionMode: boolean;
  /** Ofrece el modo selección y "Enviar por chat" (no, cuando el listado ya es un selector). */
  selectable?: boolean;
  selectedCount: number;
  hiddenColumns: CollectionFieldConfig[];
  /** Muestra la opción que reemplaza el listado por la configuración de la entidad. */
  configurable?: boolean;
}>(), { selectable: true });

const emit = defineEmits<{
  "toggle-selection": [];
  /** Enviar los seleccionados por el chat interno. */
  share: [];
  restore: [field: string];
  reset: [];
  configure: [];
}>();

const popover = ref<InstanceType<typeof PopoverType> | null>(null);
</script>
