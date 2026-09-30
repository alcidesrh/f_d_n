/**
 * Página web pública de venta de boletos (ADR-021): Vue 3 + PrimeVue 4 +
 * FormKit + Tailwind 4, sobre la API pública del backend.
 */
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'
import { es } from 'primelocale/js/es.js'
import { defaultConfig, plugin as formkit } from '@formkit/vue'
import App from './App.vue'
import Icono from './componentes/Icono.vue'
import formkitConfig from './formkit'
import { router } from './router'
import './assets/main.css'

const Marca = definePreset(Aura, {
  semantic: {
    primary: {
      50: '{blue.50}',
      100: '{blue.100}',
      200: '{blue.200}',
      300: '{blue.300}',
      400: '{blue.400}',
      500: '{blue.600}',
      600: '{blue.700}',
      700: '{blue.800}',
      800: '{blue.900}',
      900: '{blue.950}',
      950: '{blue.950}',
    },
  },
})

createApp(App)
  .use(createPinia())
  .use(router)
  .use(PrimeVue, { locale: es, theme: {
      preset: Marca,
      options: { darkModeSelector: '.oscuro', cssLayer: { name: 'primevue', order: 'theme, base, primevue, components, utilities' } },
    }, })
  .use(formkit, defaultConfig(formkitConfig()))
  // Los inputs del frontend usan <icon> (registrado global allá).
  .component('icon', Icono)
  .mount('#app')
