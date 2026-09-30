<!--
  Íconos de Tabler compilados en el build (unplugin-icons): sin llamadas a
  servicios externos. Solo los que usa la página; mismo uso que el <icon> del
  frontend (`<icon name="x" />`).
-->
<template>
  <component :is="svg" v-if="svg" aria-hidden="true" class="inline-block" :style="{ width: size, height: size }" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import ArrowRight from '~icons/tabler/arrow-right'
import ArrowsExchange from '~icons/tabler/arrows-exchange'
import Bus from '~icons/tabler/bus'
import Calendar from '~icons/tabler/calendar'
import Check from '~icons/tabler/circle-check'
import Clock from '~icons/tabler/clock'
import CreditCard from '~icons/tabler/credit-card'
import Download from '~icons/tabler/download'
import Lock from '~icons/tabler/lock'
import MapPin from '~icons/tabler/map-pin'
import Armchair from '~icons/tabler/armchair'
import AlertTriangle from '~icons/tabler/alert-triangle'
import X from '~icons/tabler/x'

const ICONOS = {
  'arrow-right': ArrowRight,
  'arrows-exchange': ArrowsExchange,
  bus: Bus,
  calendar: Calendar,
  check: Check,
  clock: Clock,
  'credit-card': CreditCard,
  download: Download,
  lock: Lock,
  'map-pin': MapPin,
  armchair: Armchair,
  alert: AlertTriangle,
  x: X,
} as const

const props = withDefaults(defineProps<{ name: string; size?: string | number }>(), { size: '1.25rem' })
const svg = computed(() => ICONOS[props.name as keyof typeof ICONOS] ?? null)
const size = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size))
</script>
