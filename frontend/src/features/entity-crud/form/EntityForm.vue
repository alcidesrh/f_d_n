<template>
  <div class="card bg-surface-50 p-[4rem]">
    <div v-if="loading" class="flex flex-col gap-4">
      <Skeleton v-for="i in 6" :key="i" height="3.5rem" />
    </div>

    <Message v-else-if="error && schema.length === 0" severity="error" :closable="false">{{
      error
    }}</Message>

    <template v-else-if="schema.length > 0">
      <Message v-if="error" severity="error" :closable="false" class="mb-4">{{ error }}</Message>
      <div class="mb-[1rem] flex justify-end">
        <SplitButton
          label="Guardar"
          :model="actions"
          :disabled="submitting || savingExtensions"
          outlined
          severity="secondary"
          @click="submitForm(formId)"
        >
          <template #dropdownicon><icon name="chevron-down" /></template>
          <template #menuitemicon="{ item }"><icon :name="String(item.icon)" /></template>
        </SplitButton>
      </div>
      <!-- Con secciones extra (p. ej. el croquis del bus): una pestaña por sección.
           Las pestañas no son lazy: todo queda montado y se guarda junto. -->
      <Tabs v-if="extensions.length" v-model:value="tab" class="entity-form-tabs">
        <TabList>
          <Tab :value="DATOS">
            <span class="flex items-center gap-2"><icon name="forms" /> Datos</span>
          </Tab>
          <Tab v-for="extension in extensions" :key="extension.key" :value="extension.key">
            <span class="flex items-center gap-2">
              <icon v-if="extension.icon" :name="extension.icon" />
              {{ extension.title }}
            </span>
          </Tab>
        </TabList>
        <TabPanels class="px-0! pb-0!">
          <TabPanel :value="DATOS">
            <FormKit
              :id="formId"
              type="form"
              :disabled="submitting"
              :actions="false"
              @submit="onSubmit"
              @submit-invalid="tab = DATOS"
            >
              <Fluid>
                <FormKitSchema :schema="schema" />
              </Fluid>
            </FormKit>
          </TabPanel>
          <TabPanel v-for="extension in extensions" :key="extension.key" :value="extension.key">
            <FormExtensionScope :extension-key="extension.key">
              <component :is="extension.component" :entity="entity" :id="id ?? null" />
            </FormExtensionScope>
          </TabPanel>
        </TabPanels>
      </Tabs>
      <FormKit
        v-else
        :id="formId"
        type="form"
        :disabled="submitting"
        :actions="false"
        @submit="onSubmit"
      >
        <Fluid>
          <FormKitSchema :schema="schema" />
        </Fluid>
      </FormKit>
      <div class="mt-4 flex justify-end">
        <Button
          label="Restablecer"
          severity="secondary"
          variant="text"
          size="small"
          :disabled="submitting"
          @click="reset"
        />
      </div>
    </template>

    <Message v-else severity="warn" :closable="false"
      >Sin campos serializables para {{ entity }}</Message
    >
  </div>
</template>

<script setup lang="ts">
/**
 * Formulario genérico de una entidad (alta sin `id`, edición con `id`). Emite
 * los resultados; la navegación la decide quien lo usa (`FormPage`).
 */
import { computed, defineAsyncComponent, provide, ref, useId } from 'vue'
import { submitForm } from '@formkit/core'
import { useConfirm } from 'primevue/useconfirm'
import {
  createEntityFormExtensionHost,
  ENTITY_FORM_EXTENSION,
  EntityFormExtensionError,
} from '@/core/entities/formExtension'
import { notify } from '@/core/notify'
import FormExtensionScope from './FormExtensionScope'
import { entityFormExtensions } from './formExtensions'
import { useEntityForm } from './useEntityForm'

const props = defineProps<{ entity: string; id?: string | number | null }>()

const emit = defineEmits<{
  submitted: [item: Record<string, unknown>]
  deleted: []
  cancel: []
  error: [message: string]
}>()

const { schema, loading, submitting, error, mode, submit, remove, reset } = useEntityForm(
  () => props.entity,
  { id: () => props.id },
)

const formId = `entity-form-${useId()}`
const confirm = useConfirm()
const message = (cause: unknown) => (cause instanceof Error ? cause.message : String(cause))

// Secciones extra de la entidad (p. ej. el croquis del bus), guardadas con el formulario.
const extensionHost = createEntityFormExtensionHost()
const savingExtensions = ref(false)
const DATOS = 'datos'
/** Pestaña visible: los datos de la entidad o la clave de una sección extra. */
const tab = ref<string>(DATOS)
provide(ENTITY_FORM_EXTENSION, extensionHost)
const extensions = computed(() =>
  (entityFormExtensions[props.entity] ?? []).map((extension) => ({
    ...extension,
    component: defineAsyncComponent(extension.component),
  })),
)

async function onSubmit(data: Record<string, unknown>) {
  const blocked = extensionHost.validate()
  if (blocked) {
    if (blocked.key) tab.value = blocked.key
    notify.error(blocked.message)
    emit('error', blocked.message)
    return
  }
  let item: Record<string, unknown>
  try {
    item = await submit(data)
  } catch (cause) {
    emit('error', message(cause))
    return
  }
  savingExtensions.value = true
  try {
    await extensionHost.afterSave(item)
  } catch (cause) {
    if (cause instanceof EntityFormExtensionError && cause.key) tab.value = cause.key
    notify.error(message(cause))
    emit('error', message(cause))
    // Al crear, el registro ya existe: se pasa igual a su edición.
    if (mode.value === 'create') emit('submitted', item)
    return
  } finally {
    savingExtensions.value = false
  }
  emit('submitted', item)
}

function askDelete() {
  confirm.require({
    header: 'Eliminar registro',
    message: `¿Eliminar este registro de ${props.entity}? No se puede deshacer.`,
    acceptProps: { label: 'Eliminar', severity: 'danger' },
    rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
    accept: async () => {
      try {
        await remove()
        emit('deleted')
      } catch (cause) {
        emit('error', message(cause))
      }
    },
  })
}

const actions = computed(() => [
  { label: 'Cancelar', icon: 'arrow-back-up', command: () => emit('cancel') },
  ...(mode.value === 'update' ? [{ label: 'Eliminar', icon: 'trash', command: askDelete }] : []),
])
</script>
