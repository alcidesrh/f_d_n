<!--
  Buscador de salidas: lista las del rango `desde`–`hasta` (AAAA-MM-DD; sin ellas,
  las de hoy) y se filtra escribiendo (hora, ruta, bus, empresa o id). Si el valor
  no está en la lista (llegó por enlace) se muestra como «Salida #id».
-->
<template>
  <Select
    :model-value="modelValue"
    :options="opciones"
    option-label="etiqueta"
    option-value="id"
    filter
    filter-match-mode="contains"
    auto-filter-focus
    :show-clear="showClear"
    :size="size"
    :loading="cargando"
    :placeholder="placeholder"
    :input-id="inputId"
    :disabled="disabled"
    empty-message="No hay salidas en esas fechas"
    empty-filter-message="Ninguna coincide"
    fluid
    @update:model-value="emit('update:modelValue', $event ?? null)"
  >
    <template #value="{ value, placeholder: ph }">
      <span v-if="value != null" class="truncate">{{ opciones.find((o) => o.id === value)?.etiqueta ?? `Salida #${value}` }}</span>
      <span v-else>{{ ph }}</span>
    </template>
  </Select>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { fetchSalidas } from '@/core/salida/api'
import { aDia, aQuery, filtroInicial, horaDe } from '@/core/salida/filtro'
import type { EstadoSalida, SalidaFila } from '@/core/salida/types'

const MAX_PAGINAS = 10

const props = withDefaults(defineProps<{ modelValue: number | null; desde?: string | null; hasta?: string | null; placeholder?: string; inputId?: string; disabled?: boolean; showClear?: boolean; size?: 'small' | 'large' }>(), {
  desde: null,
  hasta: null,
  placeholder: 'Buscar salida',
  inputId: undefined,
  disabled: false,
  showClear: true,
  size: undefined,
})
const emit = defineEmits<{ 'update:modelValue': [id: number | null] }>()

const salidas = ref<SalidaFila[]>([])
const cargando = ref(false)

const dia = (iso: string) => iso.slice(8, 10) + '/' + iso.slice(5, 7)
const multiDia = computed(() => !!props.desde && !!props.hasta && props.desde !== props.hasta)
const opciones = computed(() =>
  salidas.value.map((s) => ({
    id: s.id,
    etiqueta: [multiDia.value ? dia(s.fecha) : null, horaDe(s.fecha), s.ruta.nombre, s.bus ? `Bus ${s.bus.codigo}` : null, `#${s.id}`].filter(Boolean).join(' · '),
  })),
)

let pedido = 0
async function cargar() {
  const n = ++pedido
  cargando.value = true
  try {
    const hoy = aDia(new Date())
    const desde = props.desde ?? hoy
    const base = { ...filtroInicial(), estados: ['iniciada', 'abordando', 'programada', 'finalizada', 'cancelada'] as EstadoSalida[], desde, hasta: props.hasta ?? desde, orden: 'fecha' as const, direccion: 'asc' as const, porPagina: 100 }
    // Un día puede tener más de 100 salidas: se leen todas las páginas (con tope).
    const todas: SalidaFila[] = []
    for (let pagina = 1; pagina <= MAX_PAGINAS; pagina++) {
      const r = await fetchSalidas(aQuery({ ...base, pagina }), true)
      if (n !== pedido) return
      todas.push(...r.items)
      if (todas.length >= r.total || !r.items.length) break
    }
    salidas.value = todas
  } catch {
    if (n === pedido) salidas.value = []
  } finally {
    if (n === pedido) cargando.value = false
  }
}
watch(() => [props.desde, props.hasta], cargar, { immediate: true })
</script>
