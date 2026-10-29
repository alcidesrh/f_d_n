<!--
  Elegir un registro de una entidad con muchos (ruta, enclave…): se busca en el
  servidor al teclear (`collectionAgnostic`), nunca se carga la lista entera.
  `v-model` es el id numérico; lo ya elegido se muestra con su etiqueta aunque
  no esté entre las coincidencias actuales.
-->
<template>
  <AutoComplete
    :model-value="elegida"
    :suggestions="sugerencias"
    option-label="label"
    dropdown
    force-selection
    show-clear
    :delay="300"
    :placeholder="placeholder"
    empty-search-message="Sin coincidencias"
    size="small"
    fluid
    :disabled="disabled"
    :aria-label="ariaLabel"
    @complete="buscar($event.query)"
    @update:model-value="elegir($event)"
  />
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { idDeOpcion } from '@/core/entities/busquedaRemota'
import { graphql } from '@/core/graphql/client'
import type { AgnosticOption } from '@/core/graphql/types'

interface Opcion {
  id: number
  label: string
}

const props = defineProps<{
  /** Nombre de la entidad (`Ruta`, `Enclave`, `Usuario`, `Trayecto`). */
  entidad: string
  placeholder?: string
  ariaLabel?: string
  disabled?: boolean
}>()
const modelo = defineModel<number | null>({ required: true })

const elegida = ref<Opcion | null>(null)
const sugerencias = ref<Opcion[]>([])
const aOpcion = (o: AgnosticOption): Opcion => ({ id: idDeOpcion(o), label: o.label })

let ultima = ''
/** Solo la respuesta de lo último tecleado cuenta. */
async function buscar(query: string) {
  ultima = query
  const encontradas = (await graphql.agnosticSearch(props.entidad, { search: query.trim() })).map(
    aOpcion,
  )
  if (ultima === query) sugerencias.value = encontradas
}

function elegir(valor: unknown) {
  if (valor && typeof valor === 'object') {
    elegida.value = valor as Opcion
    modelo.value = (valor as Opcion).id
  } else {
    elegida.value = null
    modelo.value = null
  }
}

/** Un id que llega de fuera (la URL, el padre) se muestra con su etiqueta. */
watch(
  modelo,
  async (id) => {
    if (id === null) return (elegida.value = null)
    if (elegida.value?.id === id) return
    const [o] = await graphql.agnosticSearch(props.entidad, { ids: [String(id)] })
    elegida.value = o ? aOpcion(o) : null
  },
  { immediate: true },
)
</script>
