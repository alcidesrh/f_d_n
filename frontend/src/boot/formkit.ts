import { defineBoot } from "#q-app/wrappers";
import { plugin, defaultConfig } from "@formkit/vue";
import config from "../../formkit.config";

export default defineBoot(async ({ app }) => {
  app.use(plugin, defaultConfig(config));
});
