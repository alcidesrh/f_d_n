import type { BreadcrumbValue } from "types/breadcrumb";

const list: BreadcrumbValue = {
  label: "PermisoList",
  icon: "whatshot",
};
const create: BreadcrumbValue = {
  label: "PermisoCreate",
  icon: "whatshot",
};
const update: BreadcrumbValue = {
  label: "PermisoUpdate",
  icon: "whatshot",
};
const show: BreadcrumbValue = {
  label: "PermisoShow",
  icon: "whatshot",
};

export default [
  {
    name: list.label,
    path: "/permisos/",
    component: () => import("pages/permiso/PageList.vue"),
    meta: {
      breadcrumb: [list],
    },
  },
  {
    name: create.label,
    path: "/permisos/create",
    component: () => import("pages/permiso/PageCreate.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, create],
    },
  },
  {
    name: update.label,
    path: "/permisos/edit/:id",
    component: () => import("pages/permiso/PageUpdate.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, update],
    },
  },
  {
    name: show.label,
    path: "/permisos/show/:id",
    component: () => import("pages/permiso/PageShow.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, show],
    },
  },
];
