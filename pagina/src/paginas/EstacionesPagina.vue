<!--
  Estaciones por departamento, con buscador y enlace al mapa. En el
  prerender llegan por `provide('directorio')` (si el backend respondió al
  compilar); en el navegador se piden siempre al día.
-->
<template>
  <div>
    <CabeceraPagina :titulo="t('estaciones.titulo')" :entradilla="t('estaciones.intro')" />
    <div class="contenedor mt-6 flex flex-col gap-4">
      <IconField class="md:max-w-md">
        <InputIcon><icon name="search" size="1rem" /></InputIcon>
        <InputText v-model="filtro" :placeholder="t('estaciones.buscar')" fluid class="!bg-white" />
      </IconField>

      <div v-if="!estaciones" class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        <Skeleton v-for="n in 6" :key="n" height="9rem" border-radius="1rem" />
      </div>
      <p v-else-if="!grupos.length" class="panel m-0 text-sm text-muted-color">{{ t('estaciones.ninguna') }}</p>
      <div v-else class="columns-1 gap-4 md:columns-2 lg:columns-3">
        <section v-for="g in grupos" :key="g.departamento" class="panel mb-4 break-inside-avoid">
          <h2 class="m-0 mb-2 flex items-center gap-1.5 text-base font-semibold text-marca-900"><icon name="map-pin" size="1.1rem" />{{ g.departamento }}</h2>
          <ul class="m-0 flex list-none flex-col divide-y divide-surface-100 p-0">
            <li v-for="e in g.estaciones" :key="e.id" class="py-2">
              <div class="text-sm font-medium">{{ e.nombre }}</div>
              <div v-if="e.direccion" class="text-xs text-muted-color">{{ e.direccion }}</div>
              <a
                :href="mapa(e)"
                target="_blank"
                rel="noopener"
                class="mt-0.5 inline-flex items-center gap-1 text-xs no-underline"
              >{{ t('estaciones.mapa') }} <icon name="external-link" size="0.8rem" /></a>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import * as api from '@/api'
import type { EstacionDirectorio } from '@/api'
import CabeceraPagina from '@/componentes/CabeceraPagina.vue'
import { porDepartamento } from '@/modelo'

const { t, locale } = useI18n()
const estaciones = ref<EstacionDirectorio[] | null>(inject<EstacionDirectorio[] | null>('directorio', null))
const filtro = ref('')

const normalizar = (s: string) => s.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
const grupos = computed(() => {
  const q = normalizar(filtro.value.trim())
  const lista = (estaciones.value ?? []).filter((e) => !q || normalizar(`${e.nombre} ${e.departamento ?? ''} ${e.direccion ?? ''}`).includes(q))
  return porDepartamento(lista, t('buscador.otros'), locale.value)
})

const mapa = (e: EstacionDirectorio) =>
  e.latitud != null && e.longitud != null
    ? `https://www.google.com/maps/search/?api=1&query=${e.latitud},${e.longitud}`
    : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${e.direccion ?? ''} ${e.nombre}, ${e.departamento ?? ''}, Guatemala`)}`

onMounted(async () => {
  estaciones.value = (await api.directorio().catch(() => null)) ?? estaciones.value ?? []
})
</script>
