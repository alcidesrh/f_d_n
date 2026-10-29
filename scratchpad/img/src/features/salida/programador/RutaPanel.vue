<!--
  Ruta del programador (ADR-030) y, para quien no tiene empresa, la empresa
  que filtra distribuciones y buses (la salida es de la empresa de su bus).
-->
<template>
  <section class="flex flex-col gap-2" aria-labelledby="pg-ruta">
    <h2 id="pg-ruta" class="pg-titulo"><span class="pg-paso">1</span>Ruta</h2>
    <RutaSelect v-model="store.form.rutaId" placeholder="Elegir la ruta" :loading="!store.opciones" aria-label="Ruta" />
    <p v-if="ruta?.duracionMinutos" class="m-0 text-xs text-muted-color">
      {{ ruta.origen }} → {{ ruta.destino }} · {{ ruta.paradas }} paradas · duración estimada {{ duracion(duracionConClase(ruta, null) ?? ruta.duracionMinutos)
      }}<template v-if="Object.keys(ruta.duracionPorClase ?? {}).length"> (varía según la clase del bus)</template>. Un bus no se programa mientras está en otro viaje.
    </p>
    <p v-else-if="ruta" class="m-0 text-xs text-muted-color">La ruta no tiene duración estimada: solo se evita el mismo bus a la misma hora.</p>
    <label v-if="store.pideEmpresa" class="mt-2 flex flex-col gap-1">
      <span class="text-xs font-medium text-muted-color">Empresa</span>
      <Select
        v-model="store.form.empresaId"
        :options="store.opciones?.empresas ?? []"
        option-label="nombre"
        option-value="id"
        show-clear
        fluid
        placeholder="Todas las empresas"
        aria-label="Empresa"
      />
      <span class="text-xs text-muted-color">Muestra solo sus distribuciones y buses; la salida es de la empresa de su bus.</span>
    </label>
  </section>
</template>

<script setup lang="ts">
import { duracionConClase } from '@/core/salida/buses'
import RutaSelect from '../RutaSelect.vue'
import { duracion } from './formato'
import { useProgramadorStore } from './store'

const store = useProgramadorStore()
const ruta = computed(() => store.ruta)
</script>
