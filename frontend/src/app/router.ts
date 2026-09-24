import { createRouter, createWebHistory } from "vue-router";
import { useSessionStore } from "@/core/auth/session";
import { useNavigationHistoryStore } from "./layout/navigationHistory";

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
        title: "Resumen operativo",
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
      path: "/form/build",
      name: "form_build",
      component: () => import("@/pages/form/FormBuilder.vue"),
      meta: {
        title: "Constructor de formularios",
        panel: { component: () => import("@/components/formbuilder/FormBuilderPanel.vue"), width: 350 },
      },
    },
    {
      path: "/migracion",
      name: "migracion",
      component: () => import("@/pages/migracion/MigracionView.vue"),
      meta: {
        title: "Migración legado → nuevo",
        panel: { component: () => import("@/components/migracion/MigracionPanel.vue"), width: 340 },
      },
    },
    {
      path: "/configuracion/entidades",
      name: "entity-config",
      component: () => import("@/pages/config/EntityConfigEditor.vue"),
      meta: {
        title: "Configuración de entidades",
        label: "Config. entidades",
        icon: "adjustments",
      },
    },
    {
      path: "/lista/:entity",
      name: "entity-list",
      props: true,
      component: () => import("@/components/crud/List.vue"),
      meta: {
        title: "Lista de entidad",
      },
    },
    {
      path: "/form/:entity/:id?",
      name: "entity-form",
      props: true,
      component: () => import("@/components/crud/Form.vue"),
      meta: {
        title: "Formulario de entidad",
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
  const session = useSessionStore();
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

