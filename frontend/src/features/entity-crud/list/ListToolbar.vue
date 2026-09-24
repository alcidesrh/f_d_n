<template>
  <Toolbar class="rounded-none border-none! bg-transparent px-2">
    <template #start>
      <span v-if="selectionMode" class="text-sm font-medium text-surface-600"
        >{{ selectedCount }} seleccionados</span
      >
      <PageHead v-else />
    </template>
    <template #end>
      <div class="my-4 flex items-center justify-between gap-5">
        <button type="button" aria-label="Modo selección" @click="emit('toggle-selection')">
          <icon name="square-check" :class="{ 'text-primary': selectionMode }" />
        </button>
        <OverlayBadge
          v-if="hiddenColumns.length > 0"
          :value="String(hiddenColumns.length)"
          severity="primary"
          size="small"
        >
          <button
            type="button"
            :aria-label="`${hiddenColumns.length} columnas ocultas`"
            @click="popover?.toggle($event)"
          >
            <icon name="eye-off" />
          </button>
        </OverlayBadge>
        <Popover ref="popover">
          <div class="flex flex-col gap-1 p-2">
            <span class="px-2 pb-1 text-xs font-semibold text-surface-500">Columnas ocultas</span>
            <button
              v-for="column in hiddenColumns"
              :key="column.field"
              type="button"
              class="flex cursor-pointer items-center justify-between gap-6 rounded px-2 py-1 text-left text-sm text-surface-700 hover:bg-surface-100"
              @click="emit('restore', column.field)"
            >
              <span>{{ column.label ?? column.field }}</span>
              <icon name="eye" />
            </button>
          </div>
        </Popover>
        <button type="button" aria-label="Restablecer vista" @click="emit('reset')">
          <icon name="rotate-ccw" />
        </button>
      </div>
    </template>
  </Toolbar>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { Popover as PopoverType } from 'primevue'
import type { CollectionFieldConfig } from '@/core/entities/types'

defineProps<{
  selectionMode: boolean
  selectedCount: number
  hiddenColumns: CollectionFieldConfig[]
}>()

const emit = defineEmits<{ 'toggle-selection': []; restore: [field: string]; reset: [] }>()

const popover = ref<InstanceType<typeof PopoverType> | null>(null)
</script>
