import { defineRouter } from "#q-app/wrappers";
import {
  createMemoryHistory,
  createRouter,
  createWebHashHistory,
  createWebHistory,
} from "vue-router";
import routes from "./routes";

let router;
export default defineRouter(function (/* { store, ssrContext } */) {
  const createHistory = createWebHistory;
  // process.env.SERVER
  //   ? createMemoryHistory
  //   : (process.env.VUE_ROUTER_MODE === 'history' ? createWebHistory : createWebHashHistory);

  router = createRouter({
    // scrollBehavior: () => ({ left: 0, top: 0 }),
    scrollBehavior(to, from, savedPosition) {
      if (savedPosition) {
        return savedPosition;
      } else {
        return { top: 0 };
      }
    },
    routes,

    // Leave this as is and make changes in quasar.conf.js instead!
    // quasar.conf.js -> build -> vueRouterMode
    // quasar.conf.js -> build -> publicPath
    history: createHistory(process.env.VUE_ROUTER_BASE),
  });

  // router.beforeEach(async (to) => {
  //   {
  //     const entity = to.params.entity as string;
  //     if (!entity) {
  //       return true;
  //     }
  //     if (to.meta.lista) {
  //       const store = await getStore(entity);
  //       store.collection();
  //     } else if (to.meta.form) {
  //       const store = await getStore();
  //       store.getFormSchema();
  //       const id = to.params.id as string | undefined;
  //       if (id) {
  //         store.getItem(id);
  //       } else {
  //         // store.newEntity();
  //       }
  //     }
  //     return true;
  //   }
  // });
  return router;
});
export { router };
