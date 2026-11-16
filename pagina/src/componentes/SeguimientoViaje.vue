<!--
  «¿Dónde va mi bus?» (ADR-065): en la tarjeta de un viaje de «Mis compras»,
  cerca de su hora o en camino. Dice a qué hora se estima que el bus pasa
  por donde sube el cliente y llega a donde baja, cuánto atraso lleva y abre
  su posición en el mapa del teléfono. Se actualiza cada minuto; si no hay
  nada que mostrar (falta mucho, ya pasó, o la consulta falla) no ocupa lugar.
-->
<template>
  <section v-for="tr in tramos" :key="tr.salida" class="flex flex-col gap-2 rounded-xl border border-surface-200 bg-surface-50 p-3" :aria-label="t('seguimiento.titulo')">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="m-0 flex items-center gap-2 text-sm font-semibold"><icon name="bus" size="1.1rem" class="text-marca-700" />{{ t('seguimiento.titulo') }}<template v-if="tr.bus"> · {{ t('seguimiento.bus', { bus: tr.bus }) }}</template></p>
      <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="CLASE[puntualidad(tr)]">{{ etiqueta(tr) }}</span>
    </div>
    <dl v-if="extremos(tr)" class="m-0 grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
      <div v-for="(p, i) in [extremos(tr)!.sube, extremos(tr)!.baja]" :key="p.enclave">
        <dt class="text-muted-color">{{ t(i === 0 ? 'seguimiento.sube' : 'seguimiento.baja', { parada: p.nombre }) }}</dt>
        <dd class="m-0 font-medium tabular-nums">
          <template v-if="p.paso">{{ t('seguimiento.yaPaso') }}</template>
          <template v-else-if="horaMostrada(p) !== null">{{ horaDe(horaMostrada(p)!) }}<span v-if="tr.fuente === 'gps'" class="font-normal text-muted-color"> {{ t('salidas.aprox') }}</span></template>
          <template v-else>—</template>
        </dd>
      </div>
    </dl>
    <p class="m-0 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-color">
      <span v-if="tr.lectura">{{ t('seguimiento.actualizado', { hora: horaDe(tr.lectura) }) }}</span>
      <span v-else>{{ t('seguimiento.sinGps') }}</span>
      <a v-if="tr.posicion" :href="enlaceMapa(tr.posicion)" target="_blank" rel="noopener" class="font-medium text-marca-700">{{ t('seguimiento.verMapa') }}</a>
    </p>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import * as api from '@/api'
import { REGION, type Idioma } from '@/i18n'
import { hora } from '@/modelo'
import { enlaceMapa, extremos, horaMostrada, puntualidad, type Puntualidad, type SeguimientoViaje, type TramoSeguimiento } from '@/seguimiento'

const props = defineProps<{ llave: string; viaje: number }>()
const { t, locale } = useI18n()

const CLASE: Record<Puntualidad, string> = {
  aTiempo: 'bg-emerald-50 text-emerald-700',
  atrasado: 'bg-amber-50 text-amber-800',
  adelantado: 'bg-sky-50 text-sky-700',
  horario: 'bg-surface-100 text-muted-color',
}

const datos = ref<SeguimientoViaje | null>(null)
const tramos = computed(() => (datos.value?.tramos ?? []).filter((x) => x.visible && x.paradas.length > 1))
const horaDe = (epoch: number) => hora(new Date(epoch * 1000).toISOString(), REGION[locale.value as Idioma])

function etiqueta(tr: TramoSeguimiento): string {
  const p = puntualidad(tr)
  return p === 'atrasado' || p === 'adelantado' ? t(`seguimiento.${p}`, { minutos: Math.abs(tr.atrasoMinutos ?? 0) }) : t(`seguimiento.${p}`)
}

async function cargar() {
  try {
    datos.value = await api.seguimientoViaje(props.llave, props.viaje)
  } catch {
    // Sin seguimiento la tarjeta del viaje queda como estaba.
    datos.value = null
  }
}

let temporizador: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  void cargar()
  temporizador = setInterval(() => void cargar(), 60_000)
})
onBeforeUnmount(() => {
  if (temporizador) clearInterval(temporizador)
})
</script>
