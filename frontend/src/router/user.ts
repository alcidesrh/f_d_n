import type { BreadcrumbValue } from "types/breadcrumb";

const list: BreadcrumbValue = {
  label: "UserList",
  icon: "whatshot",
};
const create: BreadcrumbValue = {
  label: "UserCreate",
  icon: "whatshot",
};
const update: BreadcrumbValue = {
  label: "UserUpdate",
  icon: "whatshot",
};
const show: BreadcrumbValue = {
  label: "UserShow",
  icon: "whatshot",
};

export default [
  {
    path: "/usuarios",
    // name: 'User',
    meta: {
      label: "usuarios",
      breadcrumb: { label: "Usuarios", icon: "list" },

      //  transition: "slide-left"
    },

    children: [
      {
        name: "users",
        path: "",
        component: () => import("@/pages/user/UserCollection.vue"),
        meta: {
          // breadcrumb: { label: "Listado", icon: "list" },
        },
      },
      {
        name: "createUser",
        path: "crear",
        component: () => import("@/pages/user/UserForm.vue"),
        meta: {
          breadcrumb: { label: "Crear", icon: "person_add" },
        },
      },
      {
        name: "updateUser",
        path: "edit/:id",
        component: () => import("@/pages/user/UserForm.vue"),
        meta: {
          breadcrumb: { label: "Actualizar", icon: "person_edit" },
        },
      },
      {
        name: "account_edit",
        path: "cuenta/:id",
        component: () => import("@/pages/user/UserAccount.vue"),
        meta: {
          breadcrumb: "Cuenta",
        },
      },
    ],
  },
];
