import { createRouter, createWebHistory } from "vue-router";
import { useUserSessionStore } from "@/stores/session";
import { useNavigationHistoryStore } from "@/stores/navigationHistory";

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior() {
    return { top: 0 };
  },
  routes: [
    {
      path: "/",
      name: "dashboard",
      component: () => import("@/pages/Dashboard.vue"),
      meta: {
        layout: "default",
        title: "Resumen operativo",
        requiresAuth: true,
      },
    },
    {
      path: "/login",
      name: "login",
      component: () => import("@/pages/auth/Login.vue"),
      meta: {
        layout: "blank",
        title: "Iniciar Sesión",
        public: true,
      },
    },
    {
      path: "/demo",
      name: "demo",
      component: () => import("@/pages/form/index.vue"),
      meta: {
        layout: "formdemo",
        title: "FormKit & PrimeVue Demo",
      },
    },
    {
      path: "/demo/picklist",
      name: "picklist-preview",
      component: () => import("@/pages/preview/PickListDemo.vue"),
      meta: {
        layout: "default",
        title: "PickList Drag & Drop",
      },
    },
    {
      path: "/form/build",
      name: "form_build",
      component: () => import("@/pages/form/FormBuilder.vue"),
      meta: {
        layout: "formdemo",
        title: "FormKit & PrimeVue Demo",
      },
    },
    {
      path: "/migracion",
      name: "migracion",
      component: () => import("@/pages/migracion/MigracionView.vue"),
      meta: {
        layout: "migracion",
        title: "Migración legado → nuevo",
        requiresAuth: true,
      },
    },
    {
      path: "/configuracion/entidades",
      name: "entity-config",
      component: () => import("@/pages/config/EntityConfigEditor.vue"),
      meta: {
        layout: "default",
        title: "Configuración de entidades",
        label: "Config. entidades",
        icon: "adjustments",
        requiresAuth: true,
      },
    },
    {
      path: "/lista/:entity",
      name: "entity-list",
      props: true,
      component: () => import("@/components/crud/List.vue"),
      meta: {
        layout: "default",
        title: "Lista de entidad",
      },
    },
    {
      path: "/form/:entity",
      name: "entity-form",
      props: true,
      component: () => import("@/components/crud/Form.vue"),
      meta: {
        layout: "default",
        title: "Lista de entidad",
      },
    },
    {
      path: "/:pathMatch(.*)*",
      name: "not-found",
      component: () => import("@/pages/errors/NotFoundPage.vue"),
      meta: {
        layout: "blank",
        title: "Página no encontrada",
      },
    },
  ],
});

router.beforeEach((to) => {
  const session = useUserSessionStore();
  // DocumentDocument title
  const title = to.meta.title;
  document.title = title ? `${title} | FDN` : "FDN - Flotas de la Nación";

  // Auth guard
  const isPublic = to.meta.public === true;

  if (!session.isAuthenticated && !isPublic) {
    return { name: "login" };
  }

  if (session.isAuthenticated && isPublic) {
    return { name: "dashboard" };
  }
});

router.afterEach((to) => {
  useNavigationHistoryStore().push(to);
});

export default router;
