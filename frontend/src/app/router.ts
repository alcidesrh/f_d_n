import { createRouter, createWebHistory } from 'vue-router'
import { useSessionStore } from '@/core/auth/session'
import { useNavigationHistoryStore } from '@/app/layout/navigationHistory'

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior() {
    return { top: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/features/dashboard/DashboardPage.vue'),
      meta: {
        title: 'Resumen operativo',
      },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/features/auth/LoginPage.vue'),
      meta: {
        layout: 'blank',
        title: 'Iniciar Sesión',
        public: true,
      },
    },
    {
      path: '/form/build',
      name: 'form_build',
      component: () => import('@/features/form-builder/FormBuilderPage.vue'),
      meta: {
        title: 'Constructor de formularios',
        panel: {
          component: () => import('@/features/form-builder/FormBuilderPanel.vue'),
          width: 350,
        },
      },
    },
    {
      path: '/migracion',
      name: 'migracion',
      component: () => import('@/features/migracion/MigracionPage.vue'),
      meta: {
        title: 'Migración legado → nuevo',
        panel: { component: () => import('@/features/migracion/MigracionPanel.vue'), width: 340 },
      },
    },
    {
      path: '/configuracion/entidades',
      name: 'entity-config',
      component: () => import('@/features/entity-config/EntityConfigPage.vue'),
      meta: {
        title: 'Configuración de entidades',
        label: 'Config. entidades',
        icon: 'adjustments',
      },
    },
    {
      path: '/configuracion/menus',
      name: 'menu-builder',
      component: () => import('@/features/menu-builder/MenuBuilderPage.vue'),
      meta: {
        title: 'Menús de navegación',
        label: 'Menús',
        icon: 'sitemap',
      },
    },
    {
      path: '/lista/:entity',
      name: 'entity-list',
      props: true,
      component: () => import('@/features/entity-crud/ListPage.vue'),
      meta: {
        title: 'Lista de entidad',
      },
    },
    {
      path: '/form/:entity/:id?',
      name: 'entity-form',
      props: true,
      component: () => import('@/features/entity-crud/FormPage.vue'),
      meta: {
        title: 'Formulario de entidad',
      },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('./NotFoundPage.vue'),
      meta: {
        layout: 'blank',
        title: 'Página no encontrada',
      },
    },
  ],
})

router.beforeEach((to) => {
  const session = useSessionStore()
  // DocumentDocument title
  const title = to.meta.title
  document.title = title ? `${title} | FDN` : 'FDN - Flotas de la Nación'

  // Auth guard
  const isPublic = to.meta.public === true

  if (!session.isAuthenticated && !isPublic) {
    return { name: 'login' }
  }

  if (session.isAuthenticated && isPublic) {
    return { name: 'dashboard' }
  }
})

router.afterEach((to) => {
  useNavigationHistoryStore().push(to)
})
