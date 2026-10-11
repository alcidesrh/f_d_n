<!--
  Elegir una ruta: agrupadas por estación de salida y buscadas por palabras
  sueltas en origen, destino o nombre (`guatem santa elena`), las que más
  coinciden primero (`core/salida/rutas.ts`). Los grupos empiezan recogidos
  (salvo el de la ruta elegida) y se abren al pulsarlos; al buscar se ven
  todos abiertos. `v-model` es el id de la ruta.
-->
<template>
  <Select
    v-model="modelo"
    :options="visibles"
    option-label="nombre"
    option-value="id"
    option-group-label="origen"
    option-group-children="rutas"
    filter
    :filter-fields="['nombre', 'origen', 'destino']"
    :filter-match-mode="MODO"
    filter-placeholder="Palabras del origen o el destino"
    :virtual-scroller-options="{ itemSize: 52 }"
    scroll-height="22rem"
    fluid
    :pt="{ optionGroup: { class: '!p-0' } }"
    v-bind="$attrs"
    @filter="(e) => (busqueda = e.value)"
    @show="abrirElegido"
    @hide="busqueda = ''"
  >
    <template #optiongroup="{ option }">
      <button
        type="button"
        class="flex h-full w-full cursor-pointer items-center gap-1 border-0 bg-transparent px-3 text-left text-xs font-semibold uppercase text-muted-color hover:text-color"
        :aria-expanded="abierto(option.origen)"
        @mousedown.prevent
        @click.stop="alternar(option.origen)"
      >
        <icon :name="abierto(option.origen) ? 'expand-more' : 'chevron-right'" />
        <icon name="location-on-outline" />
        <span class="grow truncate">Sale de {{ option.origen }}</span>
        <span class="font-normal normal-case tabular-nums">{{ option.total }}</span>
      </button>
    </template>
    <template #option="{ option }">
      <div class="flex flex-col">
        <span>{{ option.nombre }}</span>
        <span class="text-xs text-muted-color">{{ option.origen }} → {{ option.destino }} · {{ option.paradas }} paradas</span>
      </div>
    </template>
  </Select>
</template>

<script setup lang="ts">
import { agruparRutas, palabras, normalizar } from '@/core/salida/rutas'
import type { RutaOpcion } from '@/core/salida/types'
import { FilterService } from '@primevue/core/api'

defineOptions({ inheritAttrs: false })

/** Coincide si alguna palabra está en el campo: el orden lo da `agruparRutas`. */
const MODO = 'rutaPorPalabras'
FilterService.register(MODO, (valor: unknown, filtro: unknown) => {
  const campo = normalizar(String(valor ?? ''))
  return palabras(String(filtro ?? '')).some((t) => campo.includes(t))
})

const props = defineProps<{ rutas: readonly RutaOpcion[] }>()
const modelo = defineModel<number | null>({ required: true })
const busqueda = ref('')
const grupos = computed(() => agruparRutas(props.rutas, busqueda.value))
const abiertos = ref(new Set<string>())
const abierto = (origen: string) => !!busqueda.value.trim() || abiertos.value.has(origen)
/** Los grupos recogidos van sin rutas (la cabecera dice cuántas tiene). */
const visibles = computed(() => grupos.value.map((g) => ({ ...g, total: g.rutas.length, rutas: abierto(g.origen) ? g.rutas : [] })))

function alternar(origen: string) {
  const s = new Set(abiertos.value)
  if (!s.delete(origen)) s.add(origen)
  abiertos.value = s
}

/** Al abrir, se ve desplegado el grupo de la ruta elegida. */
function abrirElegido() {
  const origen = props.rutas.find((r) => r.id === modelo.value)?.origen
  if (origen && !abiertos.value.has(origen)) alternar(origen)
}
</script>
