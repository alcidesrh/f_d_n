<!--
  Editor de la configuración dinámica de entidades (ADR-010).

  Izquierda: nombres de entidad (`EntityConfiguration.entityClass`). Al elegir
  uno se filtra la `EntityConfiguration` y se cargan sus dos colecciones:
  `collectionFieldConfig` (columnas del listado) y `formFields` (campos del
  formulario). Cada registro se despliega como un panel-fila con todas sus
  propiedades editables; `position` es la posición del panel y se cambia
  arrastrando el tirador. Un único botón guarda ambas listas.
-->
<template>
  <div class="flex flex-col gap-3">
    <Toolbar class="rounded-none border-none! bg-transparent px-2">
      <template #start>
        <PageHead title="" />
      </template>
      <template #end>
        <div class="flex items-center gap-4">
          <span v-if="store.dirty" class="text-sm text-amber-600">Cambios sin guardar</span>
          <span v-if="store.attrsErrors.length" class="text-sm text-red-500">
            {{ store.attrsErrors.length }} panel(es) con attrs inválido
          </span>
          <icon
            v-if="store.selected"
            name="rotate-ccw"
            lg
            title="Descartar cambios y recargar"
            @click="reload"
          />
          <Button
            label="Guardar cambios"
            icon="pi pi-save"
            size="small"
            :disabled="!store.canSave"
            :loading="store.saving"
            @click="save"
          />
        </div>
      </template>
    </Toolbar>

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[16rem_minmax(0,1fr)]">
      <div class="card p-2">
        <Listbox
          :model-value="store.selected"
          :options="store.entityClasses"
          filter
          filter-placeholder="Buscar entidad"
          empty-filter-message="Sin coincidencias"
          empty-message="Sin entidades configuradas"
          list-style="max-height: calc(100vh - 16rem)"
          @change="onSelectEntity"
        />
      </div>

      <div class="card flex min-h-[24rem] flex-col p-3">
        <Message v-if="store.error" severity="warn" class="mb-3">{{ store.error }}</Message>

        <div
          v-if="!store.selected"
          class="flex flex-1 items-center justify-center text-surface-500"
        >
          Elegí una entidad para editar su configuración.
        </div>

        <div v-else-if="store.loading" class="flex flex-1 items-center justify-center gap-2">
          <ProgressSpinner style="width: 2rem; height: 2rem" />
        </div>

        <Tabs v-else v-model:value="tab">
          <TabList>
            <Tab value="collection">Listado ({{ store.collectionFields.length }})</Tab>
            <Tab value="form">Formulario ({{ store.formFields.length }})</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="collection">
              <p class="mb-3 text-xs text-surface-500">
                Orden de las columnas del listado. Arrastrá
                <icon name="grip-vertical" class="inline align-text-bottom" /> para cambiar la
                posición.
              </p>
              <div class="overflow-x-auto pb-1">
                <SortablePanelList
                  v-model="store.collectionFields"
                  :row-height="ROW_HEIGHT"
                  :gap="GAP"
                >
                  <template #row="{ row, index }">
                    <FieldConfigPanel :field-key="row.key" :index="index" variant="collection" />
                  </template>
                </SortablePanelList>
              </div>
            </TabPanel>

            <TabPanel value="form">
              <p class="mb-3 text-xs text-surface-500">
                Orden de los campos del formulario. Arrastrá
                <icon name="grip-vertical" class="inline align-text-bottom" /> para cambiar la
                posición.
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
import { useConfirm } from 'primevue/useconfirm'
import FieldConfigPanel from './FieldConfigPanel.vue'
import SortablePanelList from '@/shared/ui/SortablePanelList.vue'
import { useEntityConfigStore } from './store'
import { notify } from '@/core/notify'

defineOptions({ name: 'EntityConfigEditor' })

/** Alto de cada panel-fila (incluye `GAP`). */
const ROW_HEIGHT = 92
const GAP = 10

const store = useEntityConfigStore()
const confirm = useConfirm()
const tab = ref('collection')

/** Pide confirmación solo si hay cambios pendientes de guardar. */
function confirmDiscard(message: string): Promise<boolean> {
  if (!store.dirty) return Promise.resolve(true)
  return new Promise((resolve) => {
    confirm.require({
      header: 'Cambios sin guardar',
      message,
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Descartar',
      rejectLabel: 'Seguir editando',
      accept: () => resolve(true),
      reject: () => resolve(false),
      onHide: () => resolve(false),
    })
  })
}

async function onSelectEntity(event: { value: string | null }) {
  const entityClass = event.value
  if (!entityClass || entityClass === store.selected) return
  const ok = await confirmDiscard(
    `Se perderán los cambios de ${store.selected} al abrir ${entityClass}.`,
  )
  if (ok) await store.select(entityClass)
}

async function reload() {
  if (await confirmDiscard('Se descartarán los cambios y se recargará la configuración.'))
    await store.reload()
}

async function save() {
  const entityClass = store.selected
  try {
    await store.save()
    notify.success(`Configuración de ${entityClass} guardada`)
  } catch (error) {
    notify.error(
      `No se pudo guardar ${entityClass}: ${error instanceof Error ? error.message : String(error)}`,
    )
  }
}

onBeforeRouteLeave(() => confirmDiscard('Vas a salir del editor y se perderán los cambios.'))

onMounted(() => store.loadEntityClasses())
</script>
