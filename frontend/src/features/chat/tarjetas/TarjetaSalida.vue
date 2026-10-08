<!--
  Cuerpo de la tarjeta de una salida: el reporte de salida calculado desde la
  venta (ocupación, desglose por canal, cobrado) y el croquis plegable.
-->
<template>
  <div class="flex flex-col gap-2.5 text-sm">
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <div class="font-semibold leading-snug">{{ d.titulo }}</div>
        <div class="text-xs text-muted-color first-letter:uppercase">
          {{ fecha(d.salida) }}<template v-if="d.bus"> · bus {{ d.bus.codigo }}</template><template v-if="d.empresa"> · {{ d.empresa.nombre }}</template>
        </div>
      </div>
      <Tag :value="estado.etiqueta" :severity="estado.severidad" class="!text-xs shrink-0" />
    </div>

    <div class="flex flex-col gap-1">
      <div class="ocupacion" role="img" :aria-label="`${ocupados} de ${r.asientos} asientos ocupados`">
        <span v-for="s in segmentos" :key="s.clave" :class="`ocupacion__${s.clave}`" :style="{ width: `${(s.n / Math.max(r.asientos, 1)) * 100}%` }" />
      </div>
      <div class="flex justify-between text-xs tabular-nums">
        <span><b>{{ ocupados }}</b> de {{ r.asientos }} ocupados</span>
        <span class="text-muted-color">{{ libres }} libres</span>
      </div>
    </div>

    <div v-if="canales.length" class="flex flex-wrap gap-1">
      <span v-for="c in canales" :key="c.clave" class="chip"><i :class="`punto ocupacion__${c.clave}`" />{{ c.etiqueta }} {{ c.n }}</span>
    </div>

    <div class="flex items-center justify-between gap-2">
      <span class="text-xs text-muted-color">Cobrado <b class="text-color tabular-nums">{{ r.ingresos?.texto ?? 'Q 0.00' }}</b></span>
      <button v-if="d.croquis.length" type="button" class="ver-croquis" :aria-expanded="croquis" @click="croquis = !croquis">
        <icon :name="croquis ? 'expand-less' : 'airline-seat-recline-normal-outline'" size="1rem" color="text-current" />{{ croquis ? 'Ocultar' : 'Croquis' }}
      </button>
    </div>
    <div v-if="croquis" class="overflow-x-auto pb-1">
      <BusMap :elementos="d.croquis" :estado="estadoAsiento" orientacion="horizontal" tamano="xs" class="justify-center" />
    </div>
  </div>
</template>

<script setup lang="ts">
import BusMap from '@/shared/bus-map/BusMap.vue'
import { etiquetaEstado } from '@/core/salida/filtro'
import type { DetalleSalida } from '@/core/salida/types'
import { estadoEnMapa } from '@/core/venta/modelo'

const props = defineProps<{ datos: unknown }>()
const d = computed(() => props.datos as DetalleSalida & { titulo: string })
const r = computed(() => d.value.resumen)
const croquis = ref(false)

const estado = computed(() => etiquetaEstado(d.value.estado))
const ocupados = computed(() => r.value.vendidos + r.value.reservados)
const libres = computed(() => Math.max(0, r.value.asientos - ocupados.value))
const estadoAsiento = computed(() => estadoEnMapa(d.value.ocupados, []))

const ETIQUETAS = { estacion: 'Estación', agencia: 'Agencia', web: 'Página', cortesia: 'Cortesía', voucher: 'Voucher' } as const
const canales = computed(() =>
  (Object.keys(ETIQUETAS) as (keyof typeof ETIQUETAS)[]).map((clave) => ({ clave, etiqueta: ETIQUETAS[clave], n: r.value.canales[clave] })).filter((c) => c.n > 0),
)
const segmentos = computed(() => [...canales.value, { clave: 'reservado', n: r.value.reservados }].filter((s) => s.n > 0))
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
.ver-croquis {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem 0.5rem;
  border-radius: 0.45rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--p-primary-color);
  cursor: pointer;
  &:hover {
    background: var(--p-surface-100);
  }
}
</style>
