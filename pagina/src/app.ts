/**
 * Arma la aplicación (cliente y prerender): Pinia, router con idioma,
 * vue-i18n, PrimeVue (tema Aura con la marca) y FormKit.
 */
import { createApp, createSSRApp, type Component } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'
import { de as pvDe } from 'primelocale/js/de.js'
import { en as pvEn } from 'primelocale/js/en.js'
import { es as pvEs } from 'primelocale/js/es.js'
import { fr as pvFr } from 'primelocale/js/fr.js'
import { it as pvIt } from 'primelocale/js/it.js'
import { defaultConfig, plugin as formkit } from '@formkit/vue'
import { changeLocale } from '@formkit/i18n'
import type { RouterHistory } from 'vue-router'
import App from './App.vue'
import Icono from './componentes/Icono.vue'
import formkitConfig from './formkit'
import { crearI18n, esIdioma, guardarIdioma, REGION, type Idioma } from './i18n'
import { crearRouter } from './router'

const LOCALES_PRIMEVUE: Record<Idioma, unknown> = { es: pvEs, en: pvEn, fr: pvFr, de: pvDe, it: pvIt }

/** Azul de la marca; naranja para las acciones de compra (`--color-acento`, ver main.css). */
const Marca = definePreset(Aura, {
  semantic: {
    primary: {
      50: '{blue.50}',
      100: '{blue.100}',
      200: '{blue.200}',
      300: '{blue.300}',
      400: '{blue.400}',
      500: '{blue.700}',
      600: '{blue.800}',
      700: '{blue.900}',
      800: '{blue.950}',
      900: '{blue.950}',
      950: '{blue.950}',
    },
    colorScheme: {
      light: {
        formField: { background: '{surface.0}', borderColor: '{surface.300}' },
        overlay: { select: { background: '{surface.0}' }, popover: { background: '{surface.0}' } },
      },
    },
  },
})

/**
 * `ssr`: para el prerender. En el navegador se monta desde cero (sin
 * hidratar): el estado de la sesión (búsqueda, carrito) cambia lo que se
 * pinta y una hidratación a medias dejaría atributos viejos.
 */
export function crearApp(idioma: Idioma, historia: RouterHistory, opciones: { ssr?: boolean; raiz?: Component } = {}) {
  const app = (opciones.ssr ? createSSRApp : createApp)(opciones.raiz ?? App)
  const pinia = createPinia()
  const i18n = crearI18n(idioma)
  const router = crearRouter(historia)

  app
    .use(pinia)
    .use(router)
    .use(i18n)
    .use(PrimeVue, {
      locale: LOCALES_PRIMEVUE[idioma],
      theme: {
        preset: Marca,
        options: { darkModeSelector: '.oscuro', cssLayer: { name: 'primevue', order: 'theme, base, primevue, components, utilities' } },
      },
    })
    .use(formkit, defaultConfig(formkitConfig(idioma)()))
    // Los inputs del frontend usan <icon> (registrado global allá).
    .component('icon', Icono)

  /** Cambia el idioma de todo (textos, PrimeVue, FormKit) según la URL. */
  function ponerIdioma(nuevo: Idioma) {
    i18n.global.locale.value = nuevo
    app.config.globalProperties.$primevue.config.locale = LOCALES_PRIMEVUE[nuevo] as never
    changeLocale(nuevo)
    if (typeof document !== 'undefined') document.documentElement.lang = nuevo
  }

  router.beforeEach((to) => {
    const nuevo = to.params.idioma
    if (esIdioma(nuevo) && nuevo !== i18n.global.locale.value) ponerIdioma(nuevo)
    if (esIdioma(nuevo) && typeof window !== 'undefined') guardarIdioma(nuevo)
  })

  return { app, router, i18n, pinia, region: () => REGION[i18n.global.locale.value as Idioma] }
}
