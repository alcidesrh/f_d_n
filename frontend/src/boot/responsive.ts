import { boot } from "quasar/wrappers";
import { responsiveService } from "src/services/ResponsiveService";
const directive = {
  mounted(el, binding) {
    const value = binding.value;

    const update = () => {
      const bp = responsiveService.state.name;

      if (value.includes(bp)) {
        el.style.display = "";
      } else {
        el.style.display = "none";
      }
    };

    update();

    window.addEventListener("resize", update);
  },
};
export default boot(({ app }) => {
  app.config.globalProperties.$responsive = responsiveService.state;

  app.provide("responsive", responsiveService.state);

  app.directive("responsive", directive);

  useGsap(); // Set greensock global variable.
});
