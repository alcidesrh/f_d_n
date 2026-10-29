<!--
  Lo que pide acción, de lo más grave a lo menos (lo ordena el backend).
  Nivel con ícono y etiqueta, no solo color. Cada alerta lleva a la
  pantalla donde se resuelve. Sin alertas, una línea que lo confirma.
-->
<template>
  <div v-if="!alertas.length" class="sin-alertas">
    <icon name="task-alt" class="text-green-700" />
    <span>Nada urgente: facturación, flota, agencias y salidas en orden.</span>
  </div>
  <ul v-else class="alertas" aria-label="Alertas">
    <li v-for="a in alertas" :key="a.codigo" class="aviso-item" :class="a.nivel">
      <icon :name="NIVELES[a.nivel].icono" class="icono" />
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-x-2">
          <span class="nivel">{{ NIVELES[a.nivel].etiqueta }}</span>
          <span class="font-semibold">{{ a.titulo }}</span>
        </div>
        <p class="mt-0.5 text-xs text-muted-color">{{ a.detalle }}</p>
      </div>
      <RouterLink v-if="a.destino" :to="a.destino" class="ir tap-target" :aria-label="`Resolver: ${a.titulo}`">
        <icon name="arrow-forward" />
      </RouterLink>
    </li>
  </ul>
</template>

<script setup lang="ts">
import type { Alerta, NivelAlerta } from "@/core/tablero/types";

defineProps<{ alertas: Alerta[] }>();

const NIVELES: Record<NivelAlerta, { etiqueta: string; icono: string }> = {
  critico: { etiqueta: "Crítico", icono: "error" },
  alerta: { etiqueta: "Atención", icono: "warning" },
  aviso: { etiqueta: "Oportunidad", icono: "lightbulb-outline" },
};
</script>

<style scoped>
.sin-alertas {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  padding: 0.75rem 1rem;
  border-radius: var(--p-content-border-radius, 0.75rem);
  border: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
}
.alertas {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2rem;
}
@container (min-width: 56rem) {
  .alertas {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.aviso-item {
  --tono: var(--estado-info);
  display: flex;
  align-items: flex-start;
  gap: 0.625rem;
  padding: 0.625rem 0.75rem;
  border-radius: 0.625rem;
  border: 1px solid var(--p-content-border-color);
  border-left: 4px solid var(--tono);
  background: var(--p-surface-0);
  /*var(--p-content-background);*/
  /*font-size: 0.875rem;*/
}
.aviso-item.critico {
  --tono: var(--estado-critico);
}
.aviso-item.alerta {
  --tono: var(--estado-alerta);
}
.icono {
  color: var(--tono);
  margin-top: 0.1rem;
}
.nivel {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color);
}
.ir {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--p-text-muted-color);
  border-radius: 999px;
  padding: 0.25rem;
}
.ir:hover {
  color: var(--p-primary-color);
  background: var(--p-content-hover-background, rgba(127, 127, 127, 0.08));
}
</style>
