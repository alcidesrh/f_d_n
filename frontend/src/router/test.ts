import type { BreadcrumbValue } from "types/breadcrumb";

const list: BreadcrumbValue = {
  label: "PermisoList",
  icon: "whatshot",
};

export default [
  {
    name: list.label,
    path: "/test/",
    component: () => import("pages/Test.vue"),
    meta: {
      breadcrumb: [list],
    },
  },
];
