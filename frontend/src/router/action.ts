import type { BreadcrumbValue } from "types/breadcrumb";

const list: BreadcrumbValue = {
  label: "ActionList",
  icon: "lock",
};
const create: BreadcrumbValue = {
  label: "ActionCreate",
  icon: "lock",
};
const update: BreadcrumbValue = {
  label: "ActionUpdate",
  icon: "lock",
};
const show: BreadcrumbValue = {
  label: "ActionShow",
  icon: "lock",
};

export default [
  {
    name: list.label,
    path: "/actions/",
    component: () => import("pages/action/PageList.vue"),
    meta: {
      breadcrumb: [list],
      requiresPermission: "admin.accion",
    },
  },
  {
    name: create.label,
    path: "/actions/create",
    component: () => import("pages/action/PageCreate.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, create],
      requiresPermission: "admin.accion.crear",
    },
  },
  {
    name: update.label,
    path: "/actions/edit/:id",
    component: () => import("pages/action/PageUpdate.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, update],
      requiresPermission: "admin.accion.editar",
    },
  },
  {
    name: show.label,
    path: "/actions/show/:id",
    component: () => import("pages/action/PageShow.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, show],
      requiresPermission: "admin.accion.ver",
    },
  },
];
