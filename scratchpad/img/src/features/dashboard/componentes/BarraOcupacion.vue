<!-- Barra fina de ocupación con su porcentaje; el tono marca baja / normal / casi llena (con texto, no solo color). -->
<template>
  <div class="ocupacion" :title="titulo">
    <div class="pista"><div class="relleno" :class="nivel ?? 'sin-dato'" :style="{ width: `${Math.round((valor ?? 0) * 100)}%` }" /></div>
    <span class="tabular-nums text-xs" :class="{ 'text-muted-color': valor === null }">{{ porciento(valor) }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { nivelOcupacion, porciento } from "@/core/tablero/formato";

const props = defineProps<{ valor: number | null; titulo?: string }>();
const nivel = computed(() => nivelOcupacion(props.valor));
</script>

<style scoped>
.ocupacion {
  display: grid;
  grid-template-columns: 1fr 2.5rem;
  align-items: center;
  gap: 0.5rem;
  min-width: 5rem;
}
.ocupacion span {
  text-align: end;
}
.pista {
  height: 0.375rem;
  border-radius: 999px;
  background: var(--p-content-border-color);
  overflow: hidden;
}
.relleno {
  height: 100%;
  border-radius: 999px;
  /*background: var(--serie-estacion);*/
  background: var(--estado-generico);
}
.relleno.baja {
  background: var(--estado-serio);
}
.relleno.alta {
  background: var(--estado-bueno);
}
</style>
