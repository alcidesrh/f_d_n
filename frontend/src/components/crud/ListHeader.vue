<template>
  <div class="flex min-w-0 flex-col gap-1">
    <div class="flex items-center gap-1">
      <span class="truncate font-semibold capitalize">{{ column.label ?? column.field }}</span>
      <i v-if="sortable" class="text-xs" :class="sortIcon" aria-hidden="true" />
      <Button
        icon="pi pi-eye-slash"
        rounded
        text
        severity="secondary"
        size="small"
        class="ml-auto h-6 w-6 shrink-0"
        :aria-label="`Ocultar columna ${column.label ?? column.field}`"
        @click="emit('hide')"
      />
    </div>
    <div @click.stop>
      <FormKitSchema v-if="filterNode" :schema="[filterNode]" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { FormKitSchema } from '@formkit/vue'
import type { FormKitSchemaNode } from '@formkit/core'
import type { CollectionFieldConfig } from '@/stores/entities/types'

defineOptions({ name: 'ListHeader' })

// onMounted(() => console.log(`ListHeader MOUNT ${Date.now()} ${props.column?.field}`))
// onBeforeUnmount(() => console.log(`ListHeader UNMOUNT ${Date.now()} ${props.column?.field}`))

const props = defineProps<{
  column: CollectionFieldConfig
  filterNode: FormKitSchemaNode | null
  sortable: boolean
  sortState: 'asc' | 'desc' | null
}>()

const emit = defineEmits<{
  hide: []
}>()

const sortIcon = computed(() => {
  if (props.sortState === 'asc') return 'pi pi-sort-amount-up-alt text-surface-500'
  if (props.sortState === 'desc') return 'pi pi-sort-amount-down text-surface-500'
  return 'pi pi-sort text-surface-300'
})
</script>
