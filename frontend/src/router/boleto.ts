import type { BreadcrumbValue } from "types/breadcrumb";

const list: BreadcrumbValue = {
  label: "UserList",
  icon: "sym_o_whatshot",
};
const create: BreadcrumbValue = {
  label: "UserCreate",
  icon: "sym_o_whatshot",
};
const update: BreadcrumbValue = {
  label: "UserUpdate",
  icon: "sym_o_whatshot",
};
const show: BreadcrumbValue = {
  label: "UserShow",
  icon: "sym_o_whatshot",
};

export default [
  {
    path: "/boletos",
    // name: 'User',
    meta: {
      label: "boletos",
      breadcrumb: { label: "boletos", icon: "sym_o_list" },

      //  transition: "slide-left"
    },

    children: [
      {
        name: "boletos",
        path: "",
        component: () => import("@/pages/user/UserCollection.vue"),
      },
      {
        name: "BoletoVenta",
        path: "emitir",
        component: () => import("@/pages/venta/BoletoVentaPage.vue"),
        meta: {
          breadcrumb: { label: "emitir", icon: "sym_o_person_add" },
        },
      },
    ],
  },
];
