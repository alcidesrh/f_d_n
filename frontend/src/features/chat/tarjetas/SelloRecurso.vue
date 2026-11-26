<!--
  Sello pegado en el borde superior izquierdo de una tarjeta, de su grupo o de
  la burbuja que las lleva: el recurso al que pertenece ("Salida",
  "Boletos"…) en su color, para distinguir de un vistazo tarjetas de distinto
  tipo intercaladas en la conversación. Va sobre un padre con `position`.
-->
<template>
  <span class="sello" :style="{ '--sello': tipo.color }">
    <icon :name="tipo.icono" size=".85rem" color="text-current" />{{ textoSello(adjunto) }}
  </span>
</template>

<script setup lang="ts">
import type { Adjunto } from '@/core/chat/types'
import { textoSello, tipoTarjeta } from './catalogo'

const props = defineProps<{ adjunto: Pick<Adjunto, 'tipo' | 'id'> }>()
const tipo = computed(() => tipoTarjeta(props.adjunto.tipo))
</script>

<style scoped>
.sello {
  position: absolute;
  top: 0;
  left: 0.6rem;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.1rem 0.5rem 0.1rem 0.4rem;
  border: 1.5px solid var(--sello);
  border-radius: 0.4rem;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.1rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
  color: var(--sello);
  background: color-mix(in srgb, var(--sello) 12%, var(--p-content-background));
  box-shadow: var(--shadow-sm);
  transform: translateY(-55%) rotate(-2deg);
  pointer-events: none;
}
</style>
