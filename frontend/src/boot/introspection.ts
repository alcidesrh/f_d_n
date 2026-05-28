import { boot } from "quasar/wrappers";
import { useSchemaStore } from "@/stores/schemaStore";

export default boot(async ({ app, router, store }) => {
  const schemaStore = useSchemaStore();
  await schemaStore.loadEntities();
});
