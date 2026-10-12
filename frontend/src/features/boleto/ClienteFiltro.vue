<!-- Filtro por cliente: NIT, documento o nombre (3+ caracteres); se filtra por el elegido. -->
<template>
  <AutoComplete
    :model-value="modelValue"
    :suggestions="sugerencias"
    option-label="label"
    :min-length="3"
    :delay="400"
    force-selection
    show-clear
    :placeholder="placeholder"
    :input-id="inputId"
    :loading="buscando"
    fluid
    @complete="buscar($event.query)"
    @update:model-value="elegir"
  >
    <template #option="{ option }">
      <div class="flex flex-col">
        <span class="font-medium">{{ option.nombre }}</span>
        <span class="text-xs text-muted-color">{{ option.detalle }}</span>
      </div>
    </template>
    <template #empty><div class="p-2 text-sm">Sin resultados</div></template>
  </AutoComplete>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { buscarClientesBoleto } from '@/core/boleto/api'
import type { ClienteFiltro } from '@/core/boleto/listado'

defineProps<{ modelValue: ClienteFiltro | null; placeholder?: string; inputId?: string }>()
const emit = defineEmits<{ 'update:modelValue': [cliente: ClienteFiltro | null] }>()

interface Opcion extends ClienteFiltro {
  nombre: string
  detalle: string
}

const sugerencias = ref<Opcion[]>([])
const buscando = ref(false)

async function buscar(q: string) {
  buscando.value = true
  try {
    sugerencias.value = (await buscarClientesBoleto(q)).map((c) => ({
      id: c.id,
      label: `${c.nit} / ${c.nombreCompleto}`,
      nombre: c.nombreCompleto,
      detalle: [`NIT ${c.nit}`, c.numeroDocumento && `Doc. ${c.numeroDocumento}`].filter(Boolean).join(' · '),
    }))
  } catch {
    sugerencias.value = []
  } finally {
    buscando.value = false
  }
}

/** Mientras se escribe llega texto: solo cuenta un cliente elegido. */
function elegir(v: unknown) {
  if (v === null || v === undefined || v === '') emit('update:modelValue', null)
  else if (typeof v === 'object') emit('update:modelValue', { id: (v as Opcion).id, label: (v as Opcion).label })
}
</script>
