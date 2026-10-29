<!--
  Cuerpo de la tarjeta de una salida: el reporte de salida calculado desde la
  venta (ocupación y desglose de los ocupados por canal) y el croquis vertical
  (en los de dos plantas, la de clase B primero).
-->
<template>
  <div class="flex flex-col gap-2.5 text-sm">
    <div class="flex items-start justify-between gap-2">
      <div class="font-semibold leading-snug">{{ d.titulo }}</div>
      <Tag :value="estado.etiqueta" :severity="estado.severidad" class="!text-xs shrink-0" />
    </div>
    <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
      <dt>Salida</dt>
      <dd class="first-letter:uppercase">{{ fecha(d.salida) }}<span v-if="d.bus" class="text-muted-color"> · bus {{ d.bus.codigo }}</span></dd>
      <template v-if="d.empresa"><dt>Empresa</dt><dd>{{ d.empresa.nombre }}</dd></template>
      <template v-if="d.creadaEn || d.creadaPor">
        <dt>Creada</dt>
        <dd><template v-if="d.creadaEn">{{ fecha(d.creadaEn) }}</template><template v-if="d.creadaPor"> por {{ d.creadaPor }}</template></dd>
      </template>
    </dl>

    <div class="flex flex-col gap-1">
      <div class="ocupacion" role="img" :aria-label="`${ocupados} de ${r.asientos} asientos ocupados`">
        <span v-for="s in desglose" :key="s.clave" :class="`ocupacion__${s.clave}`" :style="{ width: `${(s.n / Math.max(r.asientos, 1)) * 100}%` }" />
      </div>
      <div class="flex justify-between text-xs tabular-nums">
        <span><b>{{ ocupados }}</b> ocupados</span>
        <span><b>{{ libres }}</b> libres</span>
      </div>
    </div>

    <div v-if="desglose.length" class="flex flex-wrap gap-1">
      <span v-for="c in desglose" :key="c.clave" class="chip"><i :class="`punto ocupacion__${c.clave}`" />{{ c.etiqueta }} {{ c.n }}</span>
    </div>

    <div v-if="d.croquis.length" class="flex justify-center pt-1">
      <BusMap :elementos="d.croquis" :plantas="plantasClaseBPrimero(d.croquis)" :estado="estadoAsiento" tamano="sm" class="justify-center" />
    </div>
  </div>
</template>

<script setup lang="ts">
import BusMap from '@/shared/bus-map/BusMap.vue'
import { etiquetaEstado } from '@/core/salida/filtro'
import type { DetalleSalida } from '@/core/salida/types'
import { estadoEnMapa } from '@/core/venta/modelo'
import { plantasClaseBPrimero } from './croquis'

const props = defineProps<{ datos: unknown }>()
const d = computed(() => props.datos as DetalleSalida & { titulo: string })
const r = computed(() => d.value.resumen)

const estado = computed(() => etiquetaEstado(d.value.estado))
const ocupados = computed(() => r.value.vendidos + r.value.reservados)
const libres = computed(() => Math.max(0, r.value.asientos - ocupados.value))
const estadoAsiento = computed(() => estadoEnMapa(d.value.ocupados, []))

const ETIQUETAS = { estacion: 'Estación', agencia: 'Agencia', web: 'Página', cortesia: 'Cortesía', voucher: 'Voucher' } as const
const canales = computed(() =>
  (Object.keys(ETIQUETAS) as (keyof typeof ETIQUETAS)[]).map((clave) => ({ clave, etiqueta: ETIQUETAS[clave], n: r.value.canales[clave] })).filter((c) => c.n > 0),
)
/** Los ocupados por canal (cortesía y voucher aparte) y los apartados en la página. */
const desglose = computed(() => [...canales.value, { clave: 'reservado', etiqueta: 'Preventa', n: r.value.reservados }].filter((s) => s.n > 0))
const fecha = (iso: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>

<style scoped>
/* Mismos tonos que el croquis de taquilla (`shared/bus-map/busMap.css`). */
.ocupacion {
  display: flex;
  height: 0.5rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--p-surface-200);
}
.ocupacion__estacion {
  background: var(--p-blue-500);
}
.ocupacion__web {
  background: var(--p-amber-500);
}
.ocupacion__agencia {
  background: var(--p-orange-500);
}
.ocupacion__cortesia {
  background: var(--p-lime-500);
}
.ocupacion__voucher {
  background: var(--p-neutral-500);
}
.ocupacion__reservado {
  background: repeating-linear-gradient(45deg, var(--p-orange-400) 0 3px, transparent 3px 6px);
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  font-size: 0.72rem;
  background: var(--p-surface-100);
}
.punto {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
}
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
