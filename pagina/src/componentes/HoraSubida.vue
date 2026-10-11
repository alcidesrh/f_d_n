<!--
  Fecha y hora donde sube el pasajero (ADR-030): "aprox." si es estimada; si
  no se conoce, la fecha y aparte la hora a la que el bus sale de su origen.
-->
<template>
  <span class="first-letter:uppercase">{{ fechaLarga(s.salidaOrigen, region) }}<template v-if="s.salidaPrecision !== 'desconocida'">, {{ hora(s.salidaOrigen, region) }}<template v-if="s.salidaPrecision === 'estimada'"> {{ t('salidas.aprox') }}</template></template></span>
  <span v-if="s.salidaPrecision === 'desconocida'" class="block text-xs font-normal text-muted-color">{{
    t('salidas.sinHora', { origen: s.rutaOrigen, hora: hora(s.salidaOrigen, region) })
  }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { REGION, type Idioma } from '@/i18n'
import { fechaLarga, hora } from '@/modelo'
import type { HoraSubida } from '@/tipos'

defineProps<{ s: HoraSubida }>()
const { t, locale } = useI18n()
const region = computed(() => REGION[locale.value as Idioma])
</script>
