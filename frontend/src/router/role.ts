import type { BreadcrumbValue } from "types/breadcrumb";

const list: BreadcrumbValue = {
  label: "RoleList",
  icon: "whatshot",
};
const create: BreadcrumbValue = {
  label: "RoleCreate",
  icon: "whatshot",
};
const update: BreadcrumbValue = {
  label: "RoleUpdate",
  icon: "whatshot",
};
const show: BreadcrumbValue = {
  label: "RoleShow",
  icon: "whatshot",
};

export default [
  // {
  //   name: list.label,
  //   path: "/roles/",
  //   component: () => import("pages/role/PageList.vue"),
  //   meta: {
  //     breadcrumb: [list],
  //   },
  // },
  {
    name: "createRole",
    path: "/crear/role",
    component: () => import("@/pages/role/RoleForm.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, create],
    },
  },
  {
    name: update.label,
    path: "/roles/edit/:id",
    component: () => import("pages/role/PageUpdate.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, update],
    },
  },
  {
    name: show.label,
    path: "/roles/show/:id",
    component: () => import("pages/role/PageShow.vue"),
    meta: {
      breadcrumb: [{ ...list, to: { name: list.label } }, show],
    },
  },
];
