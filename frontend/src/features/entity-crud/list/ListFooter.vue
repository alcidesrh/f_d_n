<template>
  <div class="flex flex-wrap items-center justify-between gap-3 border-t p-2">
    <span v-if="localFilter" class="text-xs text-surface-500"
      >Filtro local: aplica sobre la página cargada</span
    >
    <span v-else-if="!pagination" class="text-xs text-surface-500">{{ count }} registros</span>
    <span v-else />
    <Paginator
      v-if="pagination"
      :rows="pagination.itemsPerPage"
      :first="(pagination.currentPage - 1) * pagination.itemsPerPage"
      :total-records="pagination.totalCount"
      :rows-per-page-options="[10, 25, 50]"
      template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
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

const props = defineProps<{ pagination?: PaginationState; count: number; localFilter: boolean }>()

const emit = defineEmits<{ page: [value: { page: number; rows: number }] }>()

const range = computed(() => {
  const page = props.pagination
  if (!page) return { first: 0, last: 0 }
  const first = (page.currentPage - 1) * page.itemsPerPage
  return { first: first + 1, last: Math.min(first + page.itemsPerPage, page.totalCount) }
})
</script>
