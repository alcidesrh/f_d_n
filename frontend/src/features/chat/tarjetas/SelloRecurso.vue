<!--
  Sello pegado en la esquina de una tarjeta (o de su grupo) con el recurso al
  que pertenece ("Salidas", "Boletos"…) y su color, para distinguir de un
  vistazo tarjetas de distinto tipo intercaladas en la conversación.
-->
<template>
  <span class="sello" :style="{ '--sello': tipo.color }">
    <icon :name="tipo.icono" size=".85rem" color="text-current" />{{ tipo.recurso }}
  </span>
</template>

<script setup lang="ts">
import { tipoTarjeta } from './catalogo'

const props = defineProps<{ tipo: string }>()
const tipo = computed(() => tipoTarjeta(props.tipo))
</script>

<style scoped>
.sello {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 0.25rem;
  margin: 0 0 -0.45rem 0.6rem;
  padding: 0.1rem 0.5rem 0.1rem 0.4rem;
  border: 1.5px solid var(--sello);
  border-radius: 0.4rem;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.1rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--sello);
  background: color-mix(in srgb, var(--sello) 12%, var(--p-content-background));
  box-shadow: var(--shadow-sm);
  transform: rotate(-2deg);
}
</style>
