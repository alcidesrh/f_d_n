<template>
  <GridText :text="text" :needle="filterValue" />
</template>

<script setup lang="ts">
/**
 * Celda del listado: el texto de `cellDisplay` (relaciones por `label` →
 * `nombre` → `name` → `id`, fechas formateadas, presentadores) en una línea,
 * con las coincidencias del filtro aplicado resaltadas.
 */
import { computed } from 'vue'
import GridText from '@/shared/data-grid/GridText.vue'
import type { CollectionFieldConfig } from '@/core/entities/types'
import { cellDisplay } from './listUtils'

defineOptions({ name: 'ListCell' })

const props = defineProps<{
  column: CollectionFieldConfig
  /** Entidad del listado (para el presentador de la columna). */
  entity?: string
  data: unknown
  /** Valor del filtro aplicado de la columna (solo texto/número); resalta los matches. */
  filterValue?: unknown
}>()

const text = computed(() => cellDisplay(props.data, props.column, props.entity))
</script>
