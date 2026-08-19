<template>
  <div ref="cellRef" class="max-h-[50px] overflow-y-scroll">{{ cellDisplay(data, column) }}</div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { cellDisplay, isEmptyFilterValue } from './listUtils'
import { registerCellHighlight, unregisterCellHighlight } from './cellHighlight'
import type { CollectionFieldConfig } from '@/stores/entities/types'

defineOptions({ name: 'ListCell' })

const props = defineProps<{
  column: CollectionFieldConfig
  data: unknown
  /** Valor del filtro local de la columna (solo texto/número); resalta los matches. */
  filterValue?: unknown
}>()

const cellRef = ref<HTMLElement | null>(null)
const uid = useId()

/** Clave única por instancia de celda en el registro global de highlights. */
const key = computed(() => `cell-${uid}`)

/** Offsets [inicio, fin) de las coincidencias (case-insensitive) en el texto mostrado. */
const offsets = computed<Array<[number, number]>>(() => {
  const needle = props.filterValue
  const text = cellDisplay(props.data, props.column)
  if (!text || isEmptyFilterValue(needle)) return []
  const target = String(needle).toLowerCase()
  if (!target) return []
  const matches: Array<[number, number]> = []
  const lower = text.toLowerCase()
  let index = lower.indexOf(target)
  while (index !== -1) {
    matches.push([index, index + target.length])
    index = lower.indexOf(target, index + target.length)
  }
  return matches
})

function syncHighlight() {
  const node = cellRef.value?.firstChild
  const textNode = node && node.nodeType === 3 ? (node as Text) : null
  if (!textNode) {
    unregisterCellHighlight(key.value)
    return
  }
  const ranges = offsets.value.map(([start, end]) => {
    const range = document.createRange()
    range.setStart(textNode, start)
    range.setEnd(textNode, end)
    return range
  })
  registerCellHighlight(key.value, ranges)
}

watch(offsets, syncHighlight, { flush: 'post' })
onMounted(syncHighlight)
onBeforeUnmount(() => unregisterCellHighlight(key.value))
</script>
