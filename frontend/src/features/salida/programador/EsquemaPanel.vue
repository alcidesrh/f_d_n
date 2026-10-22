<!--
  Esquema guardado del programador (ADR-024): cargar uno en el formulario,
  guardar los cambios que se le hicieron o borrarlo. Cambiar o borrar un
  esquema no toca las salidas ya creadas con él.
-->
<template>
  <section class="flex flex-col gap-2" aria-labelledby="pg-esquema">
    <h2 id="pg-esquema" class="pg-titulo"><icon name="bookmark-outline" />Esquema guardado</h2>
    <div class="flex flex-col gap-2 @xl:flex-row @xl:items-center">
      <Select
        :model-value="store.esquemaId"
        :options="store.esquemas"
        option-label="nombre"
        option-value="id"
        show-clear
        filter
        fluid
        class="@xl:flex-1"
        :placeholder="store.esquemas.length ? 'Cargar un esquema…' : 'No hay esquemas guardados'"
        :disabled="!store.esquemas.length"
        aria-label="Cargar un esquema"
        @update:model-value="store.cargarEsquema"
      >
        <template #option="{ option }">
          <div class="flex flex-col">
            <span>{{ option.nombre }}</span>
            <span class="text-xs text-muted-color"
              >{{ option.ruta.nombre }} · {{ option.momentos.length }} hora(s){{ option.intervaloDias > 1 ? ` · cada ${option.intervaloDias} días` : '' }}</span
            >
          </div>
        </template>
      </Select>
      <div v-if="store.esquema" class="flex flex-wrap gap-2">
        <Button label="Guardar cambios" size="small" :disabled="!store.cambiosEsquema || !!store.errores.length" :loading="store.guardandoEsquema" @click="store.actualizarEsquema">
          <template #icon><icon name="save-outline" class="mr-1" /></template>
        </Button>
        <Button label="Borrar" size="small" severity="danger" outlined @click="confirmar = true">
          <template #icon><icon name="delete-outline" class="mr-1" /></template>
        </Button>
      </div>
    </div>
    <p v-if="store.esquema" class="m-0 text-xs text-muted-color">
      <template v-if="store.cambiosEsquema">Tiene cambios sin guardar en «{{ store.esquema.nombre }}».</template>
      <template v-else
        >Sin cambios. Último cambio: {{ fechaHora(store.esquema.actualizadoEn) }}<template v-if="store.esquema.actualizadoPor"> por {{ store.esquema.actualizadoPor }}</template>.</template
      >
      Cambiar el esquema no toca las salidas ya creadas.
    </p>

    <Dialog v-model:visible="confirmar" modal header="Borrar esquema" class="w-[min(26rem,calc(100vw-1rem))]">
      <p class="m-0">¿Borrar el esquema «{{ store.esquema?.nombre }}»? Las salidas ya creadas con él no cambian.</p>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text @click="confirmar = false" />
        <Button label="Borrar" severity="danger" :loading="store.guardandoEsquema" @click="borrar" />
      </template>
    </Dialog>
  </section>
</template>

<script setup lang="ts">
import { useProgramadorStore } from './store'
import { fechaHora } from './formato'

const store = useProgramadorStore()
const confirmar = ref(false)

async function borrar() {
  if (await store.borrarEsquema()) confirmar.value = false
}
</script>
