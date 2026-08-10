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
      component: () => import("@/views/Dashboard.vue"),
      meta: { crumbs: ["Andén", "Resumen"], title: "Resumen operativo" },
    },
    {
      path: "/demo",
      name: "dashboard",
      component: () => import("@/views/FormKitDemo.vue"),
      meta: { crumbs: ["Andén", "Resumen"], title: "Resumen operativo" },
    },
    {
      path: "/demo-graphql",
      name: "dashboard",
      component: () => import("@/components/GraphQLOrmDemo.vue"),
      meta: { crumbs: ["Andén", "Resumen"], title: "Resumen operativo" },
    },
      {
      path: "/demo-graphql-plugin",
      name: "dashboard2",
      component: () => import("@/components/DemoPlugingGraphql.vue"),
      meta: { crumbs: ["Andén", "Resumen"], title: "Resumen operativo" },
    },
    {
      path: "/agnostic/:entity?",
      name: "agnostic-crud",
      component: () => import("@/views/AgnosticCrudPage.vue"),
      meta: { crumbs: ["Andén", "CRUD dinámico"], title: "CRUD dinámico" },
    },
    {
      path: "/:pathMatch(.*)*",
      redirect: "/",
    },
  ],
});

export default router;
