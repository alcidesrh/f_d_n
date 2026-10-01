<!-- Quiénes somos: historia, base legal, visión, misión, objetivos y valores. -->
<template>
  <div>
    <CabeceraPagina :titulo="c.titulo" :entradilla="c.entradilla" />
    <div class="contenedor mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
      <article class="panel prosa">
        <p v-for="(p, i) in c.historia" :key="i">{{ p }}</p>
      </article>
      <div class="flex flex-col gap-4">
        <section v-for="s in c.secciones" :key="s.titulo" class="panel prosa">
          <h2 class="m-0 mb-2 text-base font-semibold text-marca-900">{{ s.titulo }}</h2>
          <p v-for="(p, i) in s.parrafos ?? []" :key="`p${i}`" class="text-sm">{{ p }}</p>
          <ul v-if="s.items?.length" class="text-sm">
            <li v-for="(it, i) in s.items" :key="`i${i}`">{{ it }}</li>
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
const c = computed(() => contenido(locale.value as Idioma).nosotros)
</script>
