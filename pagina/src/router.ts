import { createRouter, createWebHistory } from 'vue-router'

export const router = createRouter({
  history: createWebHistory('/pagina/'),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    { path: '/', name: 'inicio', component: () => import('./paginas/InicioPagina.vue') },
    { path: '/salidas', name: 'salidas', component: () => import('./paginas/SalidasPagina.vue') },
    {
      path: '/recorrido/:id(\\d+)',
      name: 'recorrido',
      component: () => import('./paginas/AsientosPagina.vue'),
      props: (r) => ({ id: Number(r.params.id), trayecto: Number(r.query.trayecto) }),
    },
    { path: '/pago', name: 'pago', component: () => import('./paginas/PagoPagina.vue') },
    {
      path: '/compra/:token',
      name: 'compra',
      component: () => import('./paginas/CompraPagina.vue'),
      props: (r) => ({ token: String(r.params.token), error: r.query.error ? String(r.query.error) : null }),
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'inicio' } },
  ],
})

router.afterEach((to) => {
  const titulos: Record<string, string> = {
    salidas: 'Horarios',
    recorrido: 'Elija sus asientos',
    pago: 'Pago',
    compra: 'Su compra',
  }
  const t = titulos[String(to.name)]
  document.title = `${t ? `${t} · ` : ''}Transportes Fuente del Norte`
})
