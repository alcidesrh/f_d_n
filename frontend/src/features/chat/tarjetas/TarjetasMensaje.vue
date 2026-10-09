<!--
  Registros compartidos en un mensaje, agrupados por tipo y plegados por
  defecto (aunque sea uno): cada grupo dice cuántos son y adelanta sus
  títulos; al desplegarlo, las tarjetas vivas.
-->
<template>
  <div class="tarjetas">
    <section v-for="g in grupos" :key="g.tipo" class="grupo" :class="{ 'grupo--abierto': abiertos.has(g.tipo) }">
      <button type="button" class="grupo__cabeza" :aria-expanded="abiertos.has(g.tipo)" @click="alternar(g.tipo)">
        <span class="grupo__icono"><icon :name="tipoTarjeta(g.tipo).icono" size="1.05rem" color="text-current" /></span>
        <span class="min-w-0 flex-1">
          <span class="grupo__tipo"
            >{{ tipoTarjeta(g.tipo).nombre }}<span class="grupo__cuenta">{{ g.adjuntos.length }}</span></span
          >
          <span class="grupo__avance">{{ avance(g.adjuntos) }}</span>
        </span>
        <icon name="keyboard-arrow-down" size="1.2rem" color="text-current" class="grupo__flecha" />
      </button>
      <div v-if="abiertos.has(g.tipo)" class="grupo__cuerpo">
        <Tarjeta v-for="a in g.adjuntos" :key="`${a.tipo}:${a.id}`" :adjunto="a" />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { Adjunto } from "@/core/chat/types";
import { tipoTarjeta } from "./catalogo";
import Tarjeta from "./Tarjeta.vue";

const props = defineProps<{ adjuntos: Adjunto[] }>();

/** En el orden en que aparece cada tipo. */
const grupos = computed(() => {
  const porTipo = new Map<string, Adjunto[]>();
  for (const a of props.adjuntos) porTipo.set(a.tipo, [...(porTipo.get(a.tipo) ?? []), a]);
  return [...porTipo].map(([tipo, adjuntos]) => ({ tipo, adjuntos }));
});

const abiertos = ref(new Set<string>());
function alternar(tipo: string) {
  const s = new Set(abiertos.value);
  if (!s.delete(tipo)) s.add(tipo);
  abiertos.value = s;
}

const avance = (adjuntos: Adjunto[]) => adjuntos.map((a) => (a.estado === "ok" ? (a.datos?.titulo ?? `#${a.id}`) : `#${a.id}`)).join(" · ");
</script>

<style scoped>
.tarjetas {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.grupo {
  overflow: hidden;
  border-radius: 0.75rem;
  color: var(--p-surface-800);
  background: var(--p-surface-50);
  /*background: var(--p-content-background);*/
  /*border: 1px solid var(--p-surface-300); */
  box-shadow: var(--shadow-sm);
}
.grupo__cabeza {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  padding: 0.55rem 0.6rem 0.55rem 0.65rem;
  text-align: left;
  cursor: pointer;
  /*border-bottom: 1px solid var(--p-surface-300);*/
  &:hover {
    background: var(--p-surface-50);
  }
}
.grupo__icono {
  display: grid;
  flex: none;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.55rem;
  color: var(--p-primary-color);
  background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
}
.grupo__tipo {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--p-surface-500);
}
.grupo__cuenta {
  min-width: 1.15rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  font-size: 0.68rem;
  line-height: 1.15rem;
  text-align: center;
  letter-spacing: 0;
  color: var(--p-primary-contrast-color);
  background: var(--p-primary-color);
}
.grupo__avance {
  display: block;
  overflow: hidden;
  font-size: 0.85rem;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--p-surface-800);
}
.grupo__flecha {
  flex: none;
  color: var(--p-surface-500);
  transition: transform 0.2s var(--ease);
}
.grupo--abierto .grupo__flecha {
  transform: rotate(180deg);
}
.grupo__cuerpo {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0 0.4rem 0.4rem;
}
.grupo__cuerpo :deep(.tarjeta) {
  border-color: var(--p-surface-100);
  background: white;
  /*var(--p-surface-50);*/
}
</style>
