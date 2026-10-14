<!-- Tarjeta de una sección del tablero: título, qué responde y cómo se calcula (ayuda), acciones y contenido. -->
<template>
  <section class="tablero-panel" :aria-label="titulo">
    <header class="flex items-start gap-2">
      <div class="min-w-0 flex-1">
        <h2 class="flex items-center gap-1.5 text-sm font-semibold text-color">
          <icon v-if="icono" :name="icono" class="text-muted-color" />
          <span class="truncate">{{ titulo }}</span>
          <button v-if="ayuda" v-tooltip.top="ayuda" type="button" class="ayuda tap-target" :aria-label="`Cómo se calcula: ${titulo}`">
            <icon name="info-outline" />
          </button>
        </h2>
        <p v-if="subtitulo" class="mt-0.5 text-xs text-muted-color">{{ subtitulo }}</p>
      </div>
      <slot name="acciones" />
    </header>
    <slot />
  </section>
</template>

<script setup lang="ts">
defineProps<{ titulo: string; subtitulo?: string; ayuda?: string; icono?: string }>();
</script>

<style scoped>
.tablero-panel {
  background: var(--p-content-background);
  background: var(--p-surface-0);
  /*var(--p-content-background);*/
  /*border: 1px solid var(--p-surface-400);*/
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-width: 0;
}
.ayuda {
  display: inline-flex;
  align-items: center;
  color: var(--p-text-muted-color);
  background: none;
  border: 0;
  padding: 0;
  cursor: help;
  font-size: 0.9rem;
}
</style>
