<!--
  Cuerpo de la tarjeta de un bus: código, empresa, asientos y el croquis
  horizontal (en los de dos plantas, la de clase B primero). El croquis se
  achica si no cabe a lo ancho de la tarjeta.
-->
<template>
  <div class="flex flex-col gap-2.5 text-sm">
    <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
      <dt>Código</dt>
      <dd class="font-semibold">{{ d.codigo }}</dd>
      <template v-if="d.empresa"><dt>Empresa</dt><dd>{{ d.empresa }}</dd></template>
      <template v-if="d.clase"><dt>Clase</dt><dd>{{ d.clase }}</dd></template>
      <dt>Asientos</dt>
      <dd class="tabular-nums">{{ d.asientos }}</dd>
    </dl>
    <div v-if="d.croquis.length" ref="caja" class="overflow-x-auto pb-1">
      <BusMap :elementos="d.croquis" :plantas="plantasClaseBPrimero(d.croquis)" orientacion="horizontal" :tamano="tamano" class="items-center" />
    </div>
  </div>
</template>

<script setup lang="ts">
import BusMap from '@/shared/bus-map/BusMap.vue'
import type { ElementoCroquis } from '@/core/croquis/types'
import { plantasClaseBPrimero } from './croquis'

interface DatosBus {
  titulo: string
  codigo: string
  empresa: string | null
  clase: string | null
  asientos: number
  croquis: ElementoCroquis[]
}

const props = defineProps<{ datos: unknown }>()
const d = computed(() => props.datos as DatosBus)

/** `sm` (con números) si cabe a lo ancho; si no, `xs`. */
const caja = ref<HTMLElement>()
const ancho = ref(Infinity)
const filas = computed(() => Math.max(1, ...d.value.croquis.map((e) => e.fila)))
const tamano = computed(() => (anchoSm(filas.value) <= ancho.value ? 'sm' : 'xs'))

/** Ancho en px del croquis horizontal `sm` (celda 1.3rem, hueco 0.16rem, frente y relleno ≈ 2rem). */
const anchoSm = (n: number) => (n * 1.3 + (n - 1) * 0.16 + 2) * 16

let observador: ResizeObserver | undefined
onMounted(() => {
  if (!caja.value) return
  observador = new ResizeObserver(([e]) => (ancho.value = e!.contentRect.width))
  observador.observe(caja.value)
})
onBeforeUnmount(() => observador?.disconnect())
</script>

<style scoped>
dt {
  color: var(--p-surface-500);
  font-size: 0.78rem;
  padding-top: 0.05rem;
}
dd {
  margin: 0;
  min-width: 0;
}
</style>
