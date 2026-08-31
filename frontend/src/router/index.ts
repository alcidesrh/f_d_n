import { createRouter, createWebHistory } from "vue-router";

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
  // Update document title dynamically
  const title = to.meta.title;
  if (title) {
    document.title = `${title} | FDN`;
  } else {
    document.title = "FDN - Flotas de la Nación";
  }
});

export default router;
