<!-- Términos y condiciones de viaje y de la compra en línea. -->
<template>
  <div>
    <CabeceraPagina :titulo="c.titulo" :entradilla="c.entradilla" />
    <div class="contenedor mt-6 grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
      <nav class="hidden lg:block" :aria-label="c.titulo">
        <ul class="sticky top-24 m-0 flex list-none flex-col gap-1 p-0 text-sm">
          <li v-for="(s, i) in c.secciones" :key="i">
            <a :href="`#politica-${i}`" class="block rounded-lg px-3 py-2 text-color no-underline hover:bg-surface-100">{{ s.titulo }}</a>
          </li>
        </ul>
      </nav>
      <div class="flex flex-col gap-4">
        <section v-for="(s, i) in c.secciones" :id="`politica-${i}`" :key="i" class="panel prosa">
          <h2 class="m-0 mb-3 text-lg font-semibold text-marca-900">{{ s.titulo }}</h2>
          <p v-for="(p, j) in s.parrafos ?? []" :key="`p${j}`">{{ p }}</p>
          <ul v-if="s.items?.length">
            <li v-for="(it, j) in s.items" :key="`i${j}`">{{ it }}</li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import CabeceraPagina from '@/componentes/CabeceraPagina.vue'
import type { Idioma } from '@/i18n'
import { contenido } from '@/i18n/contenido'

const { locale } = useI18n()
const c = computed(() => contenido(locale.value as Idioma).politicas)
</script>
