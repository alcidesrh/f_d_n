import { boot } from "quasar/wrappers";
import { useGsap } from "@/composables/useGsap";

export default boot(({ app }) => {
  useGsap(); // Set greensock global variable.
});
