<!--
  Señales del croquis vistas desde arriba: el puesto del chofer (volante) y
  una puerta (escalones, vano en el costado del bus y flecha de acceso). La
  puerta se dibuja en el costado derecho; `espejo` la pasa al izquierdo.
-->
<template>
  <svg
    viewBox="0 0 40 40"
    class="signal"
    :class="`signal--${tipo}`"
    aria-hidden="true"
    focusable="false"
  >
    <g :transform="transformacion">
      <template v-if="tipo === 'chofer'">
        <rect class="signal__pad" x="3" y="3" width="34" height="34" rx="9" />
        <circle class="signal__line" cx="20" cy="19" r="11" />
        <circle class="signal__hub" cx="20" cy="19" r="3.2" />
        <path class="signal__line" d="M9.3 16.5 16.9 18.2M30.7 16.5 23.1 18.2M20 22.2V30" />
        <rect class="signal__dash" x="8" y="33" width="24" height="2.4" rx="1.2" />
      </template>
      <template v-else>
        <rect class="signal__pad" x="3" y="3" width="34" height="34" rx="6" />
        <path class="signal__step" d="M9 12h22M9 19h22M9 26h22" />
        <path class="signal__line" d="M36.5 7v26" />
        <path class="signal__arrow" d="M26 30.5h-12m4-3.5-4 3.5 4 3.5" />
      </template>
    </g>
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { TipoSenal } from '@/core/croquis/types'

const props = withDefaults(
  defineProps<{ tipo: TipoSenal; rotacion?: number; espejo?: boolean }>(),
  {
    rotacion: 0,
    espejo: false,
  },
)

const transformacion = computed(() => {
  const partes = [
    props.rotacion ? `rotate(${props.rotacion} 20 20)` : '',
    props.espejo ? 'translate(40 0) scale(-1 1)' : '',
  ].filter(Boolean)
  return partes.length ? partes.join(' ') : undefined
})
</script>

<style scoped>
.signal {
  width: 100%;
  height: 100%;
  overflow: visible;
  --sig: var(--bm-driver);
}
.signal--puerta {
  --sig: var(--bm-door);
}
.signal__pad {
  fill: color-mix(in srgb, var(--sig) 12%, var(--p-content-background));
  stroke: color-mix(in srgb, var(--sig) 45%, transparent);
  stroke-width: 1.2;
}
.signal--puerta .signal__pad {
  stroke-dasharray: 4 2.5;
}
.signal__line,
.signal__step,
.signal__arrow {
  fill: none;
  stroke: var(--sig);
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.signal__step {
  stroke-width: 1.4;
  opacity: 0.45;
}
.signal__hub,
.signal__dash {
  fill: var(--sig);
}
.signal__dash {
  opacity: 0.35;
}
</style>
