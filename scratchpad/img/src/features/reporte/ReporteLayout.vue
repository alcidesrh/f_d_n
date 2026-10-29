<!--
  Estructura común de las pantallas de reporte: parámetros a la izquierda
  (con las acciones de generar), cifras de la vista previa a la derecha y,
  en pantallas angostas, una debajo de la otra. Cada reporte pone sus campos
  en el slot por defecto, las cifras en `resumen` y los botones en `acciones`.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <!-- <Toolbar>
      <template #start><PageHead /></template>
    </Toolbar> -->

    <div class="grid gap-4 @4xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] @4xl:items-start">
      <section class="panel flex flex-col gap-5" aria-labelledby="reporte-parametros">
        <header class="flex items-start gap-3">
          <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"><icon :name="icono" lg color="text-primary" /></span>
          <div>
            <h2 id="reporte-parametros" class="m-0 text-base font-semibold">Parámetros del reporte</h2>
            <p class="m-0 mt-0.5 text-sm text-muted-color">{{ descripcion }}</p>
          </div>
        </header>

        <slot />

        <Message v-if="problema" severity="warn" size="small" :closable="false">{{ problema }}</Message>

        <div class="flex flex-wrap items-center gap-2 pt-4">
          <slot name="acciones" />
        </div>
      </section>
      <div class="flex flsex-col gap-4">
        <divider layout="vertical" />

        <aside class="panel flex flex-col gap-4" aria-live="polite" aria-labelledby="reporte-resumen">
          <h2 id="reporte-resumen" class="m-0 flex items-center gap-2 text-base font-semibold">
            <icon name="bar-chart" color="text-primary" />Vista previa
            <ProgressSpinner v-if="cargando" class="!size-4" stroke-width="6" aria-label="Consultando" />
          </h2>
          <Message v-if="error" severity="error" size="small" :closable="false">{{ error }}</Message>
          <p v-else-if="!listo" class="m-0 text-sm text-muted-color">Completa los parámetros para ver cuánto incluirá el reporte antes de generarlo.</p>
          <slot v-else name="resumen" />
        </aside>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  icono: string;
  descripcion: string;
  /** Primer problema de los parámetros (se muestra bajo el formulario). */
  problema?: string | null;
  cargando?: boolean;
  error?: string | null;
  /** Hay cifras para mostrar. */
  listo: boolean;
}>();
</script>

<style scoped>
.panel {
  background: var(--p-content-background);
  /*border: 1px solid var(--p-content-border-color);*/
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 1rem;
}
@media (min-width: 48rem) {
  .panel {
    padding: 1.25rem;
  }
}
</style>
