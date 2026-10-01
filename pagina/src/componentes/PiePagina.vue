<!-- Pie: contacto, enlaces y avisos de la venta en línea. -->
<template>
  <footer class="mt-12 bg-marca-950 text-blue-100">
    <div class="contenedor grid gap-8 py-10 md:grid-cols-3">
      <section>
        <div class="flex items-center gap-2 text-white">
          <span class="grid size-9 place-items-center rounded-xl bg-white/10"><icon name="bus" size="1.3rem" /></span>
          <span class="font-bold">Transportes {{ t('marca.nombre') }}</span>
        </div>
        <ul class="m-0 mt-4 flex list-none flex-col gap-2 p-0 text-sm">
          <li class="flex gap-2"><icon name="shield-check" size="1.1rem" class="mt-0.5 text-blue-300" />{{ t('pie.pagoSeguro') }}</li>
          <li class="flex gap-2"><icon name="download" size="1.1rem" class="mt-0.5 text-blue-300" />{{ t('pie.boletoCorreo') }}</li>
          <li class="flex gap-2"><icon name="clock" size="1.1rem" class="mt-0.5 text-blue-300" />{{ t('pie.cierre', { minutos: cierre }) }}</li>
        </ul>
      </section>

      <section>
        <h2 class="m-0 text-sm font-semibold uppercase tracking-wide text-white">{{ t('pie.atencion') }}</h2>
        <ul class="m-0 mt-3 flex list-none flex-col gap-2.5 p-0 text-sm">
          <li><a :href="CONTACTO.telefonoEnlace" class="flex items-center gap-2 text-blue-100 no-underline hover:text-white"><icon name="phone" size="1.1rem" />{{ t('pie.telefono') }}: {{ CONTACTO.telefono }}</a></li>
          <li><a :href="CONTACTO.whatsapp" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-100 no-underline hover:text-white"><icon name="brand-whatsapp" size="1.1rem" />{{ t('pie.whatsapp') }}: {{ CONTACTO.telefono }}</a></li>
          <li><a :href="CONTACTO.rentaEnlace" class="flex items-center gap-2 text-blue-100 no-underline hover:text-white"><icon name="bus" size="1.1rem" />{{ t('pie.renta') }}: {{ CONTACTO.renta }}</a></li>
          <li class="flex items-start gap-2"><icon name="map-pin" size="1.1rem" class="mt-0.5" />{{ t('pie.oficina') }}: {{ CONTACTO.direccion }}</li>
          <li><a :href="CONTACTO.facebook" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-100 no-underline hover:text-white"><icon name="brand-facebook" size="1.1rem" />Facebook</a></li>
        </ul>
      </section>

      <nav :aria-label="t('nav.menu')">
        <h2 class="m-0 text-sm font-semibold uppercase tracking-wide text-white">{{ t('nav.menu') }}</h2>
        <ul class="m-0 mt-3 grid list-none grid-cols-2 gap-2 p-0 text-sm md:grid-cols-1">
          <li v-for="e in enlaces" :key="e.nombre">
            <RouterLink :to="{ name: e.nombre, params: { idioma: locale } }" class="text-blue-100 no-underline hover:text-white">{{ t(e.texto) }}</RouterLink>
          </li>
        </ul>
      </nav>
    </div>
    <p class="contenedor m-0 border-t border-white/10 py-5 text-xs text-blue-200">{{ t('pie.derechos', { anio }) }}</p>
  </footer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CONTACTO } from '@/i18n/contenido'
import { useViaje } from '@/viaje'

const { t, locale } = useI18n()
const viaje = useViaje()
const anio = new Date().getFullYear()
const cierre = computed(() => viaje.catalogos?.cierreMinutos ?? 60)

const enlaces = [
  { nombre: 'inicio', texto: 'nav.comprar' },
  { nombre: 'servicios', texto: 'nav.servicios' },
  { nombre: 'estaciones', texto: 'nav.estaciones' },
  { nombre: 'nosotros', texto: 'nav.nosotros' },
  { nombre: 'politicas', texto: 'nav.politicas' },
  { nombre: 'contacto', texto: 'nav.contacto' },
]
</script>
