<template>
  <span v-if="typeof raw === 'boolean'" class="list-cell-bool" :class="raw ? 'is-true' : 'is-false'" role="img" :aria-label="raw ? 'Sí' : 'No'" :title="raw ? 'Sí' : 'No'">
    <icon :name="raw ? 'check' : 'close'" size="1.1rem" />
  </span>
  <GridText v-else :text="text" :needle="filterValue" />
</template>

<script setup lang="ts">
/**
 * Celda del listado: el texto de `cellDisplay` (relaciones por `label` →
 * `nombre` → `name` → `id`, fechas formateadas, presentadores) en una línea,
 * con las coincidencias del filtro aplicado resaltadas. Los booleanos, con
 * un ícono (✓ / ✕) en lugar de `true`/`false`.
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

const raw = computed(() => (props.data as Record<string, unknown> | null)?.[props.column.field])
const text = computed(() => cellDisplay(props.data, props.column, props.entity))
</script>

<style scoped>
.list-cell-bool {
  display: inline-flex;
  align-items: center;
}
.list-cell-bool.is-true {
  color: var(--c-success-ink);
}
.list-cell-bool.is-false {
  color: var(--p-text-muted-color);
}
</style>
