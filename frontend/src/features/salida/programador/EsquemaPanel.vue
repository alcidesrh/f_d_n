<!--
  Esquema guardado del programador (ADR-024): cargar uno en el formulario,
  guardar los cambios que se le hicieron o borrarlo. Cambiar o borrar un
  esquema no toca las salidas ya creadas con él.

  Modo automático (ADR-044): el programador de tareas lo mantiene desplegado
  N días adelante. Se enciende con el esquema tal como está guardado.
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

    <div v-if="store.esquema" class="flex flex-col gap-2 rounded-border border border-surface p-3">
      <div class="flex flex-wrap items-center gap-3">
        <label class="flex items-center gap-2 text-sm">
          <ToggleSwitch
            :model-value="store.esquema.automatico"
            input-id="pg-automatico"
            :disabled="store.guardandoEsquema"
            @update:model-value="(v: boolean) => store.automatizarEsquema(v, horizonte)"
          />
          Mantener programado
        </label>
        <label class="flex items-center gap-2 text-sm">
          <InputNumber
            v-model="horizonte"
            :min="HORIZONTE_MIN"
            :max="HORIZONTE_MAX"
            :disabled="store.guardandoEsquema"
            input-class="w-16"
            aria-label="Días hacia adelante"
            size="small"
          />
          días adelante
        </label>
        <Button
          v-if="store.esquema.automatico && horizonte !== store.esquema.horizonteDias"
          label="Aplicar"
          size="small"
          text
          :loading="store.guardandoEsquema"
          @click="store.automatizarEsquema(true, horizonte)"
        />
      </div>
      <Message v-if="store.esquema.pausadoMotivo" severity="warn" :closable="false" size="small">
        En pausa desde {{ fechaHora(store.esquema.pausadoEn!) }}: {{ store.esquema.pausadoMotivo }} Corríjalo y guarde el esquema para que siga.
      </Message>
      <p class="m-0 text-xs text-muted-color">
        <template v-if="store.esquema.automatico">
          Cada madrugada se crean las salidas que falten hasta {{ horizonte }} días adelante<template v-if="store.esquema.ancla">, contando el intervalo desde el {{ diaCorto(store.esquema.ancla) }}</template>.
          <template v-if="store.esquema.programadoHasta">Programado hasta el {{ diaLargo(store.esquema.programadoHasta) }}.</template>
          Lo que se anule o borre antes de esa fecha no se vuelve a crear; si un bus está ocupado, se avisa por el chat.
        </template>
        <template v-else>Encendido, el sistema crea solo las salidas de este esquema, sin que nadie tenga que programarlas.</template>
      </p>
    </div>

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
import { HORIZONTE_MAX, HORIZONTE_MIN } from '@/core/salida/programacion'
import { diaCorto, diaLargo, fechaHora } from './formato'

const store = useProgramadorStore()
const confirmar = ref(false)
const horizonte = ref(90)
watch(
  () => store.esquema?.horizonteDias,
  (h) => (horizonte.value = h ?? 90),
  { immediate: true },
)

async function borrar() {
  if (await store.borrarEsquema()) confirmar.value = false
}
</script>
