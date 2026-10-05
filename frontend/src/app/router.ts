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
        icon: 'tune',
      },
    },
    {
      path: '/configuracion/menus',
      name: 'menu-builder',
      component: () => import('@/features/menu-builder/MenuBuilderPage.vue'),
      meta: {
        title: 'Menús de navegación',
        label: 'Menús',
        icon: 'account-tree-outline',
      },
    },
    {
      path: '/venta',
      name: 'venta',
      component: () => import('@/features/venta/VentaPage.vue'),
      meta: {
        title: 'Venta de boletos',
        label: 'Venta',
        icon: 'confirmation-number-outline',
      },
    },
    {
      path: '/salidas',
      name: 'salidas',
      component: () => import('@/features/salida/SalidasPage.vue'),
      meta: {
        title: 'Salidas',
        label: 'Salidas',
        icon: 'directions-bus-outline',
      },
    },
    {
      path: '/seguimiento',
      name: 'seguimiento',
      component: () => import('@/features/seguimiento/SeguimientoPage.vue'),
      meta: {
        title: 'Buses en recorrido',
        label: 'Seguimiento',
        icon: 'location-on-outline',
      },
    },
    {
      path: '/salidas/programar',
      name: 'salidas-programar',
      component: () => import('@/features/salida/ProgramadorPage.vue'),
      meta: {
        title: 'Programar salidas',
        label: 'Programar salidas',
        icon: 'calendar-add-on-outline',
      },
    },
    {
      path: '/reporte/cuadre-venta-boletos',
      name: 'reporte-cuadre-venta-boletos',
      component: () => import('@/features/reporte/CuadreVentaBoletosPage.vue'),
      meta: {
        title: 'Cuadre de venta de boletos',
        label: 'Cuadre de venta',
        icon: 'request-quote-outline',
      },
    },
    {
      path: '/reporte/detalle-factura-boletos',
      name: 'reporte-detalle-factura-boletos',
      component: () => import('@/features/reporte/DetalleFacturaBoletosPage.vue'),
      meta: {
        title: 'Detalle de factura de boletos',
        label: 'Detalle de facturas',
        icon: 'receipt-long-outline',
      },
    },
    {
      // No empieza con /pagina: Caddy manda /pagina* a la página pública (backend).
      path: '/venta-en-linea',
      name: 'venta-en-linea',
      component: () => import('@/features/pagina-web/PaginaWebPage.vue'),
      meta: {
        title: 'Venta en línea (página web)',
        label: 'Venta en línea',
        icon: 'language',
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
