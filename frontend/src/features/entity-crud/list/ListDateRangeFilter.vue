<template>
  <div class="date-range">
    <button :id="inputId" type="button" class="date-range__trigger p-inputtext p-component p-inputtext-sm" :class="{ 'is-empty': !hasValue }" :aria-label="`Filtrar ${label}`" aria-haspopup="dialog" @click="open">
      <icon name="calendar-month-outline" size="1rem" />
      <span class="truncate">{{ hasValue ? dateRangeLabel(modelValue) : 'Rango de fechas' }}</span>
    </button>
    <button v-if="hasValue" type="button" class="date-range__clear" :aria-label="`Limpiar filtro ${label}`" @click="emit('update:modelValue', null)">
      <icon name="close" size="0.9rem" />
    </button>

    <Popover ref="popover" @hide="onHide">
      <div class="date-range__panel" role="dialog" :aria-label="`Rango de ${label}`" @keydown.esc.stop="cancel">
        <DatePicker v-model="days" selection-mode="range" inline :manual-input="false" class="date-range__calendar" />
        <div class="date-range__times">
          <div class="date-range__field">
            <span>Desde</span>
            <DatePicker v-model="fromTime" time-only inline hour-format="24" class="date-range__time" :aria-label="`Hora desde (${label})`" />
          </div>
          <div class="date-range__field">
            <span>Hasta</span>
            <DatePicker v-model="toTime" time-only inline hour-format="24" class="date-range__time" :aria-label="`Hora hasta (${label})`" />
          </div>
        </div>
        <p class="date-range__summary">{{ draftReady ? dateRangeLabel(draft) : 'Elegí el primer día (y el último, si es un rango).' }}</p>
        <div class="date-range__actions">
          <Button label="Cancelar" size="small" text severity="secondary" @click="cancel" />
          <Button label="Buscar" size="small" :disabled="!draftReady" @click="search">
            <template #icon><icon name="search" size="1rem" class="text-white" /></template>
          </Button>
        </div>
      </div>
    </Popover>
  </div>
</template>

<script setup lang="ts">
/**
 * Filtro de rango de fechas con hora: calendario de rango, hora "desde" y
 * "hasta" (24 h) y los botones Buscar (aplica) y Cancelar (descarta). Nada se
 * aplica mientras se elige; un solo día filtra ese día de 00:00 a 23:59 (o
 * las horas elegidas).
 */
import { computed, ref } from 'vue'
import type { Popover as PopoverType } from 'primevue'
import { dateRangeLabel, withTime } from './listUtils'

const props = defineProps<{ modelValue: unknown; label: string; inputId?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: [Date, Date] | null] }>()

const popover = ref<InstanceType<typeof PopoverType> | null>(null)
const days = ref<Array<Date | null> | null>(null)
const fromTime = ref<Date | null>(null)
const toTime = ref<Date | null>(null)

const current = computed<[Date | null, Date | null]>(() => {
  const value = props.modelValue
  if (!Array.isArray(value)) return [null, null]
  const valid = (d: unknown) => (d instanceof Date && !Number.isNaN(d.getTime()) ? d : null)
  return [valid(value[0]), valid(value[1])]
})
const hasValue = computed(() => Boolean(current.value[0] || current.value[1]))

const draft = computed<[Date, Date] | null>(() => {
  const start = days.value?.[0]
  if (!start) return null
  const end = days.value?.[1] ?? start
  return [withTime(start, fromTime.value, [0, 0]), withTime(end, toTime.value, [23, 59])]
})
const draftReady = computed(() => Boolean(draft.value && draft.value[0] <= draft.value[1]))

const at = (hours: number, minutes: number) => new Date(2000, 0, 1, hours, minutes)

function open(event: Event) {
  const [from, to] = current.value
  days.value = from ? [new Date(from.getFullYear(), from.getMonth(), from.getDate()), to ? new Date(to.getFullYear(), to.getMonth(), to.getDate()) : null] : null
  fromTime.value = from ? at(from.getHours(), from.getMinutes()) : at(0, 0)
  toTime.value = to ? at(to.getHours(), to.getMinutes()) : at(23, 59)
  popover.value?.toggle(event)
}

function search() {
  if (!draftReady.value || !draft.value) return
  emit('update:modelValue', draft.value)
  popover.value?.hide()
}

function cancel() {
  popover.value?.hide()
}

function onHide() {
  days.value = null
}

defineExpose({ open, search, cancel, days, fromTime, toTime })
</script>

<style scoped>
.date-range {
  position: relative;
  display: flex;
  width: 100%;
  min-width: 0;
}
.date-range__trigger {
  display: flex;
  width: 100%;
  min-width: 0;
  align-items: center;
  gap: 0.375rem;
  padding-inline-end: 1.75rem;
  cursor: pointer;
  text-align: start;
}
.date-range__trigger.is-empty {
  color: var(--p-inputtext-placeholder-color, var(--p-text-muted-color));
}
.date-range__clear {
  position: absolute;
  inset-block: 0;
  inset-inline-end: 0.375rem;
  display: grid;
  place-items: center;
  cursor: pointer;
}
.date-range__panel {
  display: flex;
  width: min(20rem, 85vw);
  flex-direction: column;
  gap: 0.625rem;
}
.date-range__calendar :deep(.p-datepicker-panel) {
  border: 0;
  padding: 0;
}
.date-range__times {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}
.date-range__time :deep(.p-datepicker-panel) {
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
  padding: 0.25rem;
}
.date-range__time :deep(.p-datepicker-time-picker) {
  padding: 0;
  border: 0;
}
.date-range__field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.date-range__summary {
  margin: 0;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}
.date-range__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
