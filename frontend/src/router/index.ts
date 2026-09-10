import { createRouter, createWebHistory } from "vue-router";
import { useUserSessionStore } from "@/stores/session";

const router = createRouter({
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
        crumbs: ["Andén", "Resumen"],
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
        crumbs: ["Andén", "Demostración"],
        title: "FormKit & PrimeVue Demo",
      },
    },
    {
      path: "/demo/picklist",
      name: "picklist-preview",
      component: () => import("@/pages/preview/PickListDemo.vue"),
      meta: {
        layout: "default",
        crumbs: ["Andén", "Demostración", "PickList"],
        title: "PickList Drag & Drop",
      },
    },
    {
      path: "/form/build",
      name: "form_build ",
      component: () => import("@/pages/form/FormBuilder.vue"),
      meta: {
        layout: "formdemo",
        crumbs: ["Andén", "Demostración"],
        title: "FormKit & PrimeVue Demo",
      },
    },
    {
      path: "/menu",
      name: "menu-list",
      component: () => import("@/pages/menu/MenuList.vue"),
      meta: {
        layout: "default",
        crumbs: ["Andén", "Gestión", "Menús"],
        title: "Gestión de menús",
        requiresAuth: true,
      },
    },
    {
      path: "/menu/crear",
      name: "menu-create",
      component: () => import("@/pages/menu/MenuForm.vue"),
      meta: {
        layout: "default",
        crumbs: ["Andén", "Gestión", "Menús", "Crear"],
        title: "Crear menú",
        requiresAuth: true,
      },
    },
    {
      path: "/menu/:id/editar",
      name: "menu-edit",
      component: () => import("@/pages/menu/MenuForm.vue"),
      meta: {
        layout: "default",
        crumbs: ["Andén", "Gestión", "Menús", "Editar"],
        title: "Editar menú",
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
        crumbs: ["Andén", "Listado"],
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
        crumbs: ["Andén", "Listado"],
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

export default router;
