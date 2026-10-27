<!--
  Editor de la configuración dinámica de una entidad (ADR-010), en su propia
  pantalla: `/configuracion/entidades/:entidad`. Se cargan los campos del
  formulario (`formFields`) y las configuraciones del listado de la entidad,
  cada una con sus roles, columnas y opciones (ADR-043); se edita una a la vez. Cada registro se despliega como un
  panel-fila con todas sus propiedades editables; `position` es la posición del
  panel y se cambia arrastrando el tirador. Un único botón guarda ambas listas.

  No muestra el listado de la entidad (ese es `/lista/:entidad`).
-->
<template>
  <div class="flex flex-col gap-3">
    <div class="card flex min-h-[24rem] flex-col p-3">
      <Message v-if="store.error" severity="warn" class="mb-3">{{ store.error }}</Message>

      <div v-if="store.loading" class="flex flex-1 items-center justify-center gap-2">
        <ProgressSpinner style="width: 2rem; height: 2rem" />
      </div>

      <div v-else-if="store.selected" class="relative">
        <div class="mb-2 flex flex-wrap items-center justify-end gap-3 lg:absolute lg:right-0 lg:top-0 lg:z-10 lg:mb-0">
          <span v-if="store.dirty" class="text-sm text-amber-600">Cambios sin guardar</span>
          <span v-if="store.attrsErrors.length" class="text-sm text-red-500"> {{ store.attrsErrors.length }} valor(es) inválido(s) </span>
          <icon name="restart-alt" lg title="Descartar cambios y recargar" @click="reload" />
          <Button :label="tieneListado ? 'Volver al listado' : 'Todas las entidades'" severity="secondary" size="small" @click="volver">
            <template #icon><icon name="arrow-back" /></template>
          </Button>
          <Button label="Guardar cambios" size="small" :disabled="!store.canSave" :loading="store.saving" @click="save">
            <template #icon><icon name="save-outline" class="text-inherit" /></template>
          </Button>
        </div>
        <Tabs v-model:value="tab">
          <TabList>
            <Tab value="collection">Listado ({{ store.collectionFields.length }})</Tab>
            <Tab value="form">Formulario ({{ store.formFields.length }})</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="collection">
              <ListConfigBar />
              <ListOptionsPanel />
              <p class="mb-3 text-xs text-surface-500">
                Orden de las columnas del listado. Arrastrá
                <icon name="drag-indicator" class="inline align-text-bottom" /> para cambiar la posición.
              </p>
              <div class="overflow-x-auto pb-1">
                <SortablePanelList v-model="store.collectionFields" :row-height="ROW_HEIGHT" :gap="GAP">
                  <template #row="{ row, index }">
                    <FieldConfigPanel :field-key="row.key" :index="index" variant="collection" />
                  </template>
                </SortablePanelList>
              </div>
            </TabPanel>

            <TabPanel value="form">
              <p class="mb-3 text-xs text-surface-500">
                Orden de los campos del formulario. Arrastrá
                <icon name="drag-indicator" class="inline align-text-bottom" /> para cambiar la posición.
              </p>
              <div class="overflow-x-auto pb-1">
                <SortablePanelList v-model="store.formFields" :row-height="ROW_HEIGHT" :gap="GAP">
                  <template #row="{ row, index }">
                    <FieldConfigPanel :field-key="row.key" :index="index" variant="form" />
                  </template>
                </SortablePanelList>
              </div>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from "primevue/useconfirm";
import { useRouter } from "vue-router";
import { entityNameFromSlug, entitySlug } from "@/core/entities/slug";
import FieldConfigPanel from "./FieldConfigPanel.vue";
import ListConfigBar from "./ListConfigBar.vue";
import ListOptionsPanel from "./ListOptionsPanel.vue";
import SortablePanelList from "@/shared/ui/SortablePanelList.vue";
import { useEntityConfigStore } from "./store";
import { useSchemaStore } from "@/core/entities/schema";
import { notify } from "@/core/notify";

defineOptions({ name: "EntityConfigEditor" });

const props = defineProps<{
  /** Slug de la entidad (`boleto-asiento`). */
  entidad: string;
}>();

/** Alto de cada panel-fila (incluye `GAP`). */
const ROW_HEIGHT = 92;
const GAP = 10;

const store = useEntityConfigStore();
const schema = useSchemaStore();
const confirm = useConfirm();
const router = useRouter();
const tab = ref("collection");

/** Sin colección GraphQL (p. ej. BusSenal, Parada) no hay listado al que volver: va al inicio. */
const tieneListado = computed(() => Boolean(schema.find(store.selected)?.queryCollection));

/** Pide confirmación solo si hay cambios pendientes de guardar. */
function confirmDiscard(message: string): Promise<boolean> {
  if (!store.dirty) return Promise.resolve(true);
  return new Promise((resolve) => {
    confirm.require({
      header: "Cambios sin guardar",
      message,
      icon: "pi pi-exclamation-triangle",
      acceptLabel: "Descartar",
      rejectLabel: "Seguir editando",
      accept: () => resolve(true),
      reject: () => resolve(false),
      onHide: () => resolve(false),
    });
  });
}

/** Carga la configuración de la entidad de la ruta; si no existe, vuelve al inicio. */
async function abrir(slug: string) {
  await store.loadEntityClasses();
  const entityClass = entityNameFromSlug(slug);
  if (!store.entityClasses.includes(entityClass)) {
    notify.error(`No existe la entidad ${entityClass}`);
    void router.replace({ name: "dashboard" });
    return;
  }
  tab.value = "collection";
  await store.select(entityClass);
}

function volver() {
  if (tieneListado.value) void router.push({ name: "entity-list", params: { entity: entitySlug(store.selected) } });
  else void router.push({ name: "dashboard" });
}

async function reload() {
  if (await confirmDiscard("Se descartarán los cambios y se recargará la configuración.")) await store.reload();
}

async function save() {
  const entityClass = store.selected;
  try {
    await store.save();
    notify.success(`Configuración de ${entityClass} guardada`);
  } catch (error) {
    notify.error(`No se pudo guardar ${entityClass}: ${error instanceof Error ? error.message : String(error)}`);
  }
}

watch(() => props.entidad, abrir, { immediate: true });

onBeforeRouteLeave(() => confirmDiscard("Vas a salir del editor y se perderán los cambios."));
onBeforeRouteUpdate(() => confirmDiscard(`Se perderán los cambios de ${store.selected} al cambiar de entidad.`));
</script>
