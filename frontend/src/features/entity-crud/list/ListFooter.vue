<template>
  <div class="flex flex-wrap items-center justify-between gap-3 border-t p-2">
    <span v-if="!pagination" class="text-xs text-surface-500">{{ count }} registros</span>
    <span v-else />
    <Paginator
      v-if="pagination"
      :rows="pagination.itemsPerPage"
      :first="(pagination.currentPage - 1) * pagination.itemsPerPage"
      :total-records="pagination.totalCount"
      :rows-per-page-options="[10, 25, 50]"
      :template="PAGINATOR_TEMPLATE"
      @page="(event) => emit('page', { page: event.page + 1, rows: event.rows })"
    >
      <template #end>
        <span class="ml-[10px] text-surface-500">
          <b>{{ range.first }}</b> al <b>{{ range.last }}</b> de <b>{{ pagination.totalCount }}</b>
        </span>
      </template>
    </Paginator>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PaginationState } from '@/core/entities/types'

/**
 * Por ancho de viewport (PrimeVue lo resuelve con CSS): debajo de `md`
 * (767px = 48rem - 1px) solo anterior/siguiente: el rango "1 al 10 de 57"
 * ya dice dónde se está.
 */
const PAGINATOR_TEMPLATE = {
  '767px': 'PrevPageLink NextPageLink RowsPerPageDropdown',
  default: 'FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown',
}

const props = defineProps<{ pagination?: PaginationState; count: number }>()

const emit = defineEmits<{ page: [value: { page: number; rows: number }] }>()

const range = computed(() => {
  const page = props.pagination
  if (!page) return { first: 0, last: 0 }
  const first = (page.currentPage - 1) * page.itemsPerPage
  return { first: first + 1, last: Math.min(first + page.itemsPerPage, page.totalCount) }
})
</script>
