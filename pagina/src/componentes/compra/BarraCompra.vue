<!--
  Barra fija abajo con lo elegido (ida y regreso), el total acumulado y
  "Pagar asientos". Siempre a la vista mientras se eligen asientos.
-->
<template>
  <div class="fixed inset-x-0 bottom-0 z-40 border-t border-surface-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-6px_20px_-8px_rgb(15_23_42/0.18)] backdrop-blur">
    <div class="contenedor flex items-center gap-3 py-3">
      <div class="min-w-0 flex-1">
        <div v-if="viaje.cantidad > 0" class="flex items-baseline gap-2">
          <span class="text-xs text-muted-color">{{ t('barra.total') }}</span>
          <span class="text-xl font-bold tabular-nums text-marca-900">{{ quetzales(viaje.totalCentavos, region) }}</span>
        </div>
        <p v-else class="m-0 truncate text-sm font-semibold text-marca-900">{{ ruta }}</p>
        <p class="m-0 truncate text-xs text-muted-color">
          <template v-if="viaje.cantidad > 0">
            <template v-for="(r, i) in resumen" :key="r.sentido">
              <span v-if="i" aria-hidden="true"> · </span>
              <span class="font-medium text-color">{{ t(`salidas.${r.sentido}`) }}:</span> {{ r.numeros || '—' }}
            </template>
          </template>
          <template v-else>{{ t('barra.elijaAsientos') }}</template>
        </p>
      </div>
      <Button type="button" severity="secondary" outlined class="shrink-0" :disabled="ocupado" @click="emit('cancelar')">
        <icon name="x" size="1rem" />
        <span>{{ t('barra.cancelar') }}</span>
      </Button>
      <Button
        class="boton-compra shrink-0"
        size="large"
        :loading="ocupado"
        :disabled="!!viaje.falta || ocupado"
        :aria-describedby="viaje.falta && viaje.cantidad > 0 ? 'barra-falta' : undefined"
        @click="emit('pagar')"
      >
        <icon name="lock" size="1.1rem" />
        <span>{{ ocupado ? t('barra.apartando') : t('barra.pagar') }}</span>
      </Button>
    </div>
    <p v-if="viaje.falta && viaje.cantidad > 0" id="barra-falta" class="contenedor m-0 -mt-1 pb-2 text-xs font-medium text-acento-700">
      {{ viaje.falta === 'regreso' ? t('barra.faltaRegreso') : t('barra.faltaIda') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { REGION, type Idioma } from '@/i18n'
import { quetzales } from '@/modelo'
import { useViaje } from '@/viaje'

defineProps<{ ocupado: boolean }>()
const emit = defineEmits<{ pagar: []; cancelar: [] }>()
const { t, locale } = useI18n()
const viaje = useViaje()

const ruta = computed(() => {
  const { origen, destino } = viaje.busqueda
  return origen && destino ? `${viaje.nombres[origen] ?? ''} → ${viaje.nombres[destino] ?? ''}` : ''
})
const region = computed(() => REGION[locale.value as Idioma])
const resumen = computed(() =>
  viaje.sentidos.map((s) => ({ sentido: s, numeros: viaje.elegida[s]?.asientos.map((a) => a.numero).join(', ') ?? '' })),
)
</script>
