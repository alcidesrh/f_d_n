<!--
  Vista previa del programador (ADR-024): lo que haría «Crear» con el formulario
  actual, pedida sola al backend; marca las que ya existen (no se duplican),
  chocan con otro viaje del bus o ya pasaron. Solo se crean las nuevas. Al
  crear, el aviso dice lo creado y lo omitido, y el programador se limpia.
-->
<template>
  <section class="flex flex-col gap-3" aria-labelledby="pg-previa" aria-live="polite">
    <h2 id="pg-previa" class="pg-titulo"><icon name="visibility-outline" />Vista previa</h2>

    <ul v-if="store.errores.length" class="m-0 flex flex-col gap-1 pl-4 text-sm text-muted-color">
      <li v-for="e in store.errores" :key="e">{{ e }}</li>
    </ul>
    <template v-else>
      <Message v-if="store.errorPrevia" severity="error" :closable="false">{{ store.errorPrevia }}</Message>
      <Skeleton v-else-if="!store.previa" height="6rem" />
      <template v-else>
        <div class="flex flex-wrap gap-2" :class="{ 'opacity-60': store.cargandoPrevia }">
          <Tag v-for="t in resumenPrevia(store.previa.resumen)" :key="t.estado" :severity="t.severidad" :value="t.texto" />
        </div>
        <PlanDias :items="store.previa.items" class="max-h-[28rem] overflow-auto" :class="{ 'opacity-60': store.cargandoPrevia }" />
      </template>
    </template>

    <div class="flex flex-col gap-2 border-t border-surface-200 pt-3">
      <div class="flex items-center gap-2">
        <Checkbox v-model="store.guardar" binary input-id="pg-guardar" />
        <label for="pg-guardar" class="text-sm font-medium">Guardar también como esquema</label>
      </div>
      <InputText v-if="store.guardar" v-model="store.nombreEsquema" placeholder="Nombre, p. ej. «Xela–Guate diario»" maxlength="100" fluid aria-label="Nombre del esquema" />
      <p class="m-0 text-xs text-muted-color">Guarda la ruta, las horas con su bus y el intervalo; los días se eligen cada vez que se usa.</p>
    </div>

    <Button :label="etiquetaCrear" :disabled="!puedeCrear" :loading="store.creando" @click="store.crear">
      <template #icon><icon name="calendar-add-on-outline" class="mr-1" /></template>
    </Button>
  </section>
</template>

<script setup lang="ts">
import { resumenPrevia } from '@/core/salida/plan'
import PlanDias from './PlanDias.vue'
import { useProgramadorStore } from './store'

const store = useProgramadorStore()

const nuevas = computed(() => store.previa?.resumen.nueva ?? 0)
const etiquetaCrear = computed(() => {
  if (!store.previa || store.errores.length) return 'Crear salidas'
  if (nuevas.value === 0) return store.form.guardarComo ? 'Solo guardar el esquema' : 'No hay salidas nuevas'
  return nuevas.value === 1 ? 'Crear 1 salida' : `Crear ${nuevas.value} salidas`
})
const puedeCrear = computed(() => !store.errores.length && !!store.previa && !store.cargandoPrevia && (nuevas.value > 0 || !!store.form.guardarComo))
</script>
