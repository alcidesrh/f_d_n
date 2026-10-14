<!--
  Reportes "Boleto Manifiesto", "Boleto Manifiesto Interno" y "Boleto Manifiesto Piloto": se elige la salida
  (de las de un día) y se descarga el PDF del manifiesto. Mismo reporte que
  "Ver/PDF" en /salidas y "Manif. piloto" en /salidas/chequeo. Permiso `salida.ver`.
-->
<template>
  <div class="@container flex flex-col gap-4">
    <section class="panel mx-auto flex w-full max-w-2xl flex-col gap-5">
      <header class="flex items-start gap-3">
        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"><icon :name="info.icono" lg color="text-primary" /></span>
        <div>
          <h2 class="m-0 text-base font-semibold">{{ info.titulo }}</h2>
          <p class="m-0 mt-0.5 text-sm text-muted-color">{{ info.descripcion }}</p>
        </div>
      </header>

      <div class="grid gap-4 @lg:grid-cols-[11rem_minmax(0,1fr)]">
        <label class="flex flex-col gap-1.5">
          <span class="text-sm font-medium">Fecha de salida</span>
          <DatePicker v-model="fecha" date-format="dd/mm/yy" show-icon icon-display="input" :manual-input="false" fluid input-id="manifiesto-fecha" @update:model-value="salidaId = null" />
        </label>
        <label class="flex min-w-0 flex-col gap-1.5">
          <span class="text-sm font-medium">Salida</span>
          <SalidaSelect v-model="salidaId" :desde="dia" :hasta="dia" input-id="manifiesto-salida" />
        </label>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Button label="Descargar manifiesto" :disabled="!salidaId" :loading="generando" class="shrink-0 whitespace-nowrap" @click="descargar">
          <template #icon><icon name="download" class="mr-1.5" /></template>
        </Button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { aDia } from '@/core/salida/filtro'
import type { TipoManifiesto } from '@/core/salida/types'
import SalidaSelect from '@/shared/ui/SalidaSelect.vue'
import { descargarManifiesto } from '@/features/salida/manifiesto'

const props = defineProps<{ tipo: TipoManifiesto }>()

const TEXTOS: Record<TipoManifiesto, { icono: string; titulo: string; descripcion: string }> = {
  boleto: { icono: 'description-outline', titulo: 'Manifiesto de pasajeros', descripcion: 'Boletos de la salida por lugar de emisión, con importes del día y prepagados.' },
  interno: { icono: 'assignment-outline', titulo: 'Manifiesto interno', descripcion: 'Boletos de la salida por lugar de emisión, con importes, para uso interno de la empresa.' },
  piloto: { icono: 'sports-motorsports-outline', titulo: 'Manifiesto del piloto', descripcion: 'Pasajeros por asiento con nacionalidad, documento y espacio para las firmas.' },
}
const info = computed(() => TEXTOS[props.tipo])

const fecha = ref<Date | null>(new Date())
const dia = computed(() => aDia(fecha.value ?? new Date()))
const salidaId = ref<number | null>(null)
const generando = ref(false)

async function descargar() {
  if (!salidaId.value) return
  generando.value = true
  try {
    await descargarManifiesto(salidaId.value, props.tipo)
  } finally {
    generando.value = false
  }
}
</script>

<style scoped>
.panel {
  background: var(--p-content-background);
  border-radius: var(--p-content-border-radius, 0.75rem);
  padding: 1rem;
}
@media (min-width: 48rem) {
  .panel {
    padding: 1.25rem;
  }
}
</style>
