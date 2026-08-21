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
      meta: {
        layout: 'default',
        crumbs: ['Andén', 'Resumen'],
        title: 'Resumen operativo',
        requiresAuth: true,
      },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/auth/Login.vue'),
      meta: {
        layout: 'blank',
        title: 'Iniciar Sesión',
      },
    },
    {
      path: '/demo',
      name: 'demo',
      component: () => import('@/views/FormKitDemo.vue'),
      meta: {
        layout: 'formdemo',
        crumbs: ['Andén', 'Demostración'],
        title: 'FormKit & PrimeVue Demo',
      },
    },
    {
      path: '/entity-form/:entity?',
      name: 'entity-form',
      component: () => import('@/views/FormKitDemo.vue'),
      meta: {
        layout: 'default',
        crumbs: ['Andén', 'Formulario'],
        title: 'Formulario dinámico',
      },
    },
    {
      path: '/lista/:entity',
      name: 'entity-list',
      props: true,
      component: () => import('@/components/crud/List.vue'),
      meta: {
        layout: 'formdemo',
        crumbs: ['Andén', 'Listado'],
        title: 'Lista de entidad',
      },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/pages/errors/NotFoundPage.vue'),
      meta: {
        layout: 'blank',
        title: 'Página no encontrada',
      },
    },
  ],
})

router.beforeEach((to) => {
  // Update document title dynamically
  const title = to.meta.title
  if (title) {
    document.title = `${title} | FDN`
  } else {
    document.title = 'FDN - Flotas de la Nación'
  }
})

export default router
