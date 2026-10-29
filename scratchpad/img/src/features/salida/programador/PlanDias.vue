<!--
  Las salidas de un plan por día: la vista previa (qué se crearía y qué se
  omite) o el resultado de crear (las creadas con su número). Un día con
  omitidas o con pocos días en total se muestra abierto.
-->
<template>
  <div class="flex flex-col gap-2">
    <details v-for="g in dias" :key="g.dia" class="plan-dia" :open="dias.length <= 3 || g.problemas > 0">
      <summary class="flex cursor-pointer flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium">
        <span>{{ diaLargo(g.dia) }}</span>
        <span class="text-muted-color">· {{ g.items.length }}</span>
        <Tag v-if="etiquetaDia(g)" :severity="etiquetaDia(g)!.severidad" :value="etiquetaDia(g)!.texto" class="!text-xs" />
      </summary>
      <ul class="m-0 mt-2 flex list-none flex-col gap-1.5 p-0 text-sm">
        <li v-for="(it, j) in g.items" :key="j" class="plan-item">
          <span class="tabular-nums">{{ horaDe(it.fecha) }}</span>
          <span class="font-medium">Bus {{ it.bus }}</span>
          <span class="flex items-center gap-1.5 justify-self-end">
            <span v-if="it.id" class="text-xs tabular-nums text-muted-color">#{{ it.id }}</span>
            <Tag :severity="ESTADO_ITEM[estadoItem(it)].severidad" :value="ESTADO_ITEM[estadoItem(it)].etiqueta" class="!text-xs" />
          </span>
          <span v-if="it.choque" class="plan-item__choque text-xs text-muted-color">
            {{ it.estado === 'existe' ? 'Ya existía' : 'Choca con' }}
            <template v-if="it.choque.id">la salida #{{ it.choque.id }}</template><template v-else>otra hora de esta programación</template>: {{ it.choque.ruta }},
            {{ fechaHora(it.choque.fecha) }}
          </span>
        </li>
      </ul>
    </details>
  </div>
</template>

<script setup lang="ts">
import { horaDe } from '@/core/salida/filtro'
import { ESTADO_ITEM, estadoItem, etiquetaDia, porDia } from '@/core/salida/plan'
import type { ItemPlan } from '@/core/salida/types'
import { diaLargo, fechaHora } from './formato'

const props = defineProps<{ items: readonly ItemPlan[] }>()
const dias = computed(() => porDia(props.items))
</script>

<style scoped>
.plan-dia {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.5rem);
}
.plan-item {
  display: grid;
  grid-template-columns: 3rem minmax(0, 1fr) auto;
  align-items: baseline;
  column-gap: 0.5rem;
}
.plan-item__choque {
  grid-column: 2 / -1;
}
</style>
