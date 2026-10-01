<template>
  <div class="flex min-h-dvh flex-col">
    <a href="#principal" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:p-2">{{ t('nav.comprar') }}</a>
    <Encabezado />
    <main id="principal" class="flex-1">
      <RouterView />
    </main>
    <PiePagina />
  </div>
</template>

<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useCarrito } from './carrito'
import Encabezado from './componentes/Encabezado.vue'
import PiePagina from './componentes/PiePagina.vue'
import type { Idioma } from './i18n'
import { aplicarHead, head } from './seo'
import { useViaje } from './viaje'

const { t, locale } = useI18n()
const route = useRoute()
const carrito = useCarrito()
const viaje = useViaje()

onMounted(() => {
  void carrito.refrescar()
  void viaje.cargarCatalogos()
})

// Título, descripción y versiones en otros idiomas al navegar.
watch(
  () => [route.fullPath, locale.value] as const,
  () => {
    if (typeof document === 'undefined' || !route.meta.seo) return
    const ruta = route.path.replace(/^\/[a-z]{2}\/?/, '').replace(/\/$/, '')
    aplicarHead(head(t, { idioma: locale.value as Idioma, pagina: route.meta.seo, ruta, origen: window.location.origin, indexable: !!route.meta.indexable }))
  },
  { immediate: true },
)
</script>
