/**
 * Rutas de la página (ADR-023): todas llevan el idioma (`/es/`, `/en/`…).
 * Las informativas (`ESTATICAS`) se prerenderizan en cada idioma para los
 * buscadores; el pago y la compra no se indexan.
 */
import { createRouter, type RouteRecordRaw, type RouterHistory } from 'vue-router'
import { esIdioma, idiomaInicial } from './i18n'
import type { PaginaSeo } from './seo'

declare module 'vue-router' {
  interface RouteMeta {
    seo?: PaginaSeo
    indexable?: boolean
  }
}

/** Páginas públicas, indexables y prerenderizadas (ruta sin idioma). */
export const ESTATICAS: Array<{ ruta: string; nombre: PaginaSeo }> = [
  { ruta: '', nombre: 'inicio' },
  { ruta: 'servicios', nombre: 'servicios' },
  { ruta: 'estaciones', nombre: 'estaciones' },
  { ruta: 'nosotros', nombre: 'nosotros' },
  { ruta: 'politicas', nombre: 'politicas' },
  { ruta: 'contacto', nombre: 'contacto' },
]

const COMPONENTES: Record<string, () => Promise<unknown>> = {
  inicio: () => import('./paginas/InicioPagina.vue'),
  servicios: () => import('./paginas/ServiciosPagina.vue'),
  estaciones: () => import('./paginas/EstacionesPagina.vue'),
  nosotros: () => import('./paginas/NosotrosPagina.vue'),
  politicas: () => import('./paginas/PoliticasPagina.vue'),
  contacto: () => import('./paginas/ContactoPagina.vue'),
}

const hijas: RouteRecordRaw[] = [
  ...ESTATICAS.map(({ ruta, nombre }) => ({
    path: ruta,
    name: nombre,
    component: COMPONENTES[nombre]!,
    meta: { seo: nombre, indexable: true },
  })),
  { path: 'pago', name: 'pago', component: () => import('./paginas/PagoPagina.vue'), meta: { seo: 'pago', indexable: false } },
  {
    path: 'compra/:token',
    name: 'compra',
    component: () => import('./paginas/CompraPagina.vue'),
    props: (r) => ({ token: String(r.params.token), error: r.query.error ? String(r.query.error) : null }),
    meta: { seo: 'compra', indexable: false },
  },
]

export function crearRouter(historia: RouterHistory) {
  const router = createRouter({
    history: historia,
    scrollBehavior: (to, from, guardada) => {
      if (guardada) return guardada
      if (to.hash) return { el: to.hash, top: 80, behavior: 'smooth' }
      // En el flujo de compra (inicio con otra búsqueda) no se salta arriba.
      if (to.name === from.name && to.name === 'inicio') return false
      return { top: 0 }
    },
    routes: [
      { path: '/:idioma(es|en|fr|de|it)', children: hijas },
      // Rutas de la versión anterior de esta página.
      { path: '/compra/:token', redirect: (to) => ({ name: 'compra', params: { idioma: idiomaInicial(), token: to.params.token } }) },
      { path: '/pago', redirect: () => ({ name: 'pago', params: { idioma: idiomaInicial() } }) },
      { path: '/:resto(.*)*', redirect: (to) => {
        const primero = String(to.params.resto?.[0] ?? '')
        return { name: 'inicio', params: { idioma: esIdioma(primero) ? primero : idiomaInicial() } }
      } },
    ],
  })
  return router
}
