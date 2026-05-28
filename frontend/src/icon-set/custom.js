import { h } from "vue";

export default {
  name: "custom",

  // este método le dice a QIcon qué renderizar
  iconMapFn(name) {
    if (name.startsWith("my-")) {
      const real = name.replace("my-", "");
      return {
        cls: "", // sin clases internas
        content: h("span", { class: "name" }, real), // tu HTML custom
      };
    }

    return null;
  },
};


// import custom from "../icon-set/custom.js";

// export default ({ app }) => {
//   app.config.globalProperties.$q.iconSet = custom;
// };

