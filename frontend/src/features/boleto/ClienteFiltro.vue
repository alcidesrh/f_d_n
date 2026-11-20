<!-- Filtro «Comprado por»: NIT, documento o nombre (3+ caracteres); se filtra por el cliente elegido (quien compró la venta). -->
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
    input-class="w-full"
    :loading="buscando"
    @complete="buscar($event.query)"
    @update:model-value="elegir"
  >
    <template #option="{ option }">
      <div class="flex flex-col">
        <span class="font-medium">{{ option.nombreCompleto }}</span>
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
  nombreCompleto: string
  detalle: string
}

const sugerencias = ref<Opcion[]>([])
const buscando = ref(false)
let consulta = 0

async function buscar(q: string) {
  const n = ++consulta
  buscando.value = true
  try {
    const r = await buscarClientesBoleto(q)
    if (n === consulta) {
      sugerencias.value = r.map((c) => ({
        id: c.id,
        label: `${c.nit} / ${c.nombreCompleto}`,
        nombreCompleto: c.nombreCompleto,
        detalle: [`NIT ${c.nit}`, c.numeroDocumento && `Doc. ${c.numeroDocumento}`]
          .filter(Boolean)
          .join(' · '),
      }))
    }
  } catch {
    if (n === consulta) sugerencias.value = []
  } finally {
    if (n === consulta) buscando.value = false
  }
}

/** Mientras se escribe llega texto: solo cuenta un cliente elegido de la lista. */
function elegir(v: unknown) {
  if (v === null || v === undefined || v === '') emit('update:modelValue', null)
  else if (typeof v === 'object') {
    const o = v as Opcion
    emit('update:modelValue', { id: o.id, label: o.label })
  }
}
</script>
