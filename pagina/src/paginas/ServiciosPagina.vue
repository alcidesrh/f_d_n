<!-- Servicios: clases de bus, renta y encomiendas (anclas `#id` desde el inicio). -->
<template>
  <div>
    <CabeceraPagina :titulo="c.titulo" :entradilla="c.entradilla" />
    <div class="contenedor mt-6 grid gap-4 md:grid-cols-2">
      <section v-for="s in c.lista" :id="s.id" :key="s.id" class="panel flex flex-col gap-3">
        <div class="flex items-center gap-3">
          <span class="grid size-11 shrink-0 place-items-center rounded-xl" :class="s.id.startsWith('oro') ? 'bg-amber-100 text-amber-800' : 'bg-marca-50 text-marca-800'">
            <icon :name="ICONOS[s.id]" size="1.4rem" />
          </span>
          <h2 class="m-0 text-lg font-semibold">{{ s.nombre }}</h2>
        </div>
        <p class="m-0 text-sm leading-relaxed text-muted-color">{{ s.texto }}</p>
        <ul class="m-0 flex list-none flex-wrap gap-1.5 p-0">
          <li v-for="d in s.destacados" :key="d" class="rounded-full bg-surface-100 px-2.5 py-1 text-xs font-medium">{{ d }}</li>
        </ul>
        <div v-if="s.id === 'renta'" class="mt-auto">
          <a :href="CONTACTO.rentaEnlace" class="inline-flex items-center gap-1.5 text-sm font-semibold no-underline"><icon name="phone" size="1rem" />{{ CONTACTO.renta }}</a>
        </div>
        <div v-else-if="s.id !== 'encomiendas'" class="mt-auto">
          <RouterLink :to="{ name: 'inicio', params: { idioma: locale } }" class="inline-flex items-center gap-1 text-sm font-semibold no-underline">{{ t('nav.comprar') }} <icon name="arrow-right" size="1rem" /></RouterLink>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import CabeceraPagina from '@/componentes/CabeceraPagina.vue'
import type { Idioma } from '@/i18n'
import { CONTACTO, contenido, type Servicio } from '@/i18n/contenido'

const { t, locale } = useI18n()
const c = computed(() => contenido(locale.value as Idioma).servicios)
const ICONOS: Record<Servicio['id'], string> = {
  'oro-gran-lujo': 'crown',
  oro: 'star',
  platino: 'armchair',
  economico: 'bus',
  renta: 'users',
  encomiendas: 'package',
}
</script>
