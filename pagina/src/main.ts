/**
 * Página web pública de venta de boletos (ADR-021, ADR-023): Vue 3 +
 * PrimeVue 4 + FormKit + Tailwind 4 + vue-i18n, sobre la API pública del
 * backend. El idioma sale de la URL (`/pagina/es/…`).
 */
import { createWebHistory } from 'vue-router'
import { crearApp } from './app'
import { esIdioma, idiomaInicial } from './i18n'
import './assets/main.css'

const primero = window.location.pathname.replace(/^\/pagina\/?/, '').split('/')[0]
const { app, router } = crearApp(esIdioma(primero) ? primero : idiomaInicial(), createWebHistory('/pagina/'))

void router.isReady().then(() => app.mount('#app'))
