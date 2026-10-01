<!-- Encabezado fijo: marca, navegación (cajón en móvil), idioma y asientos apartados. -->
<template>
  <header class="sticky top-0 z-20 border-b border-surface-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
    <div class="contenedor flex h-14 items-center justify-between gap-2 md:h-16">
      <RouterLink :to="{ name: 'inicio', params: { idioma } }" class="flex min-w-0 items-center gap-2 no-underline">
        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-marca-900 text-white">
          <icon name="bus" size="1.3rem" />
        </span>
        <span class="min-w-0 leading-tight">
          <span class="block truncate font-bold text-marca-900">{{ t('marca.nombre') }}</span>
          <span class="block truncate text-xs text-muted-color">{{ t('marca.lema') }}</span>
        </span>
      </RouterLink>

      <nav :aria-label="t('nav.menu')" class="hidden items-center gap-1 lg:flex">
        <RouterLink
          v-for="e in enlaces"
          :key="e.nombre"
          :to="{ name: e.nombre, params: { idioma } }"
          class="rounded-full px-3 py-2 text-sm font-medium text-color no-underline hover:bg-surface-100"
          active-class="!text-primary"
          exact-active-class="!bg-marca-50"
        >
          {{ t(e.texto) }}
        </RouterLink>
      </nav>

      <div class="flex shrink-0 items-center gap-1">
        <RouterLink
          v-if="carrito.asientos > 0 && route.name !== 'pago'"
          :to="{ name: 'pago', params: { idioma } }"
          class="flex h-9 items-center gap-1 rounded-full bg-acento-500 px-3 text-sm font-semibold text-white no-underline"
          :aria-label="t('pago.titulo')"
        >
          <icon name="armchair" size="1rem" />
          {{ carrito.asientos }}
        </RouterLink>
        <SelectorIdioma class="hidden md:block" />
        <button
          type="button"
          class="grid size-10 place-items-center rounded-full text-color hover:bg-surface-100 lg:hidden"
          :aria-label="t('nav.menu')"
          :aria-expanded="menu"
          @click="menu = true"
        >
          <icon name="menu" size="1.4rem" />
        </button>
      </div>
    </div>

    <Drawer v-model:visible="menu" position="right" :header="t('nav.menu')" class="!w-[min(22rem,88vw)]">
      <nav class="flex flex-col gap-1" :aria-label="t('nav.menu')">
        <RouterLink
          v-for="e in enlaces"
          :key="e.nombre"
          :to="{ name: e.nombre, params: { idioma } }"
          class="flex items-center gap-3 rounded-xl px-3 py-3 text-base text-color no-underline hover:bg-surface-100"
          exact-active-class="!bg-marca-50 !text-primary font-semibold"
          @click="menu = false"
        >
          <icon :name="e.icono" class="text-muted-color" />
          {{ t(e.texto) }}
        </RouterLink>
      </nav>
      <div class="mt-6 border-t border-surface-200 pt-4">
        <p class="m-0 mb-2 text-xs font-semibold uppercase tracking-wide text-muted-color">{{ t('nav.idioma') }}</p>
        <SelectorIdioma lista @elegido="menu = false" />
      </div>
    </Drawer>
  </header>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useCarrito } from '@/carrito'
import SelectorIdioma from './SelectorIdioma.vue'

const { t, locale } = useI18n()
const route = useRoute()
const carrito = useCarrito()
const menu = ref(false)
const idioma = computed(() => locale.value)

const enlaces = [
  { nombre: 'inicio', texto: 'nav.comprar', icono: 'ticket' },
  { nombre: 'servicios', texto: 'nav.servicios', icono: 'star' },
  { nombre: 'estaciones', texto: 'nav.estaciones', icono: 'map-pin' },
  { nombre: 'nosotros', texto: 'nav.nosotros', icono: 'users' },
  { nombre: 'politicas', texto: 'nav.politicas', icono: 'info' },
  { nombre: 'contacto', texto: 'nav.contacto', icono: 'mail' },
]
</script>
