import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior() {
    return { top: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/Dashboard.vue'),
      meta: { crumbs: ['Andén', 'Resumen'], title: 'Resumen operativo' },
    },
    {
      path: '/demo',
      name: 'demo',
      component: () => import('@/views/FormKitDemo.vue'),
      meta: { crumbs: ['Andén', 'Resumen'], title: 'Resumen operativo' },
    },
    {
      path: '/lista/:entity',
      name: 'entity-list',
      props: true,
      component: () => import('@/components/crud/List.vue'),
      meta: { crumbs: ['Andén', 'Listado'], title: 'Lista:' },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

export default router
